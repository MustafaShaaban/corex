<?php

/**
 * Access REST contracts for editable CoreX abilities and access requests.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\AccessPolicy;
use Corex\Access\CorexAbility;
use Corex\Access\RoleAbilityStore;
use Corex\Boot;
use Corex\Config\Access\AccessController;
use Corex\Config\Access\AccessRequestRepository;
use Corex\Config\Access\AccessTables;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Notifications\NotificationTable;
use Corex\Database\Schema\Migrator;
use Corex\Operations\Confirmation;

function accessRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $method === 'GET' ? $request->set_query_params($payload) : $request->set_body_params($payload);

    return $request;
}

/**
 * The editor role's explicit effect for the ability the role test changes, as the raw row.
 *
 * @return array<string,string>|null Null when there is no row, which is a state too: it means inherit.
 */
function editorFormsGrant(Migrator $migrator): ?array
{
    global $wpdb;

    return $wpdb->get_row($wpdb->prepare(
        'SELECT * FROM ' . $migrator->fullName(AccessTables::ROLE_GRANTS) . ' WHERE role_key = %s AND ability_key = %s',
        'editor',
        CorexAbility::MANAGE_FORMS,
    ), ARRAY_A);
}

beforeEach(function () {
    $this->container = Boot::app()->container();
    foreach ($this->container->make(AccessTables::class)->schemas() as $schema) {
        $this->container->make(\Corex\Database\Schema\Migrator::class)->create($schema);
    }

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($admins[0] ?? 0));
    $this->controller = $this->container->make(AccessController::class);

    // The role test below gives the editor role an ability, on a developer's real install. Keep
    // what it overwrites, and the newest audit event, so what the test writes can be told from the
    // install's own history afterwards.
    global $wpdb;
    $this->migrator = $this->container->make(Migrator::class);
    $this->editorGrant = editorFormsGrant($this->migrator);
    $this->lastActivityId = (int) $wpdb->get_var('SELECT MAX(id) FROM ' . $this->migrator->fullName(ActivityTable::NAME));
});

afterEach(function () {
    global $wpdb;
    $activity = $this->migrator->fullName(ActivityTable::NAME);
    $grants = $this->migrator->fullName(AccessTables::ROLE_GRANTS);

    // Put the editor role back as it was. This used to stay granted: every editor on the install
    // could manage forms because a test had said so.
    if (editorFormsGrant($this->migrator) !== $this->editorGrant) {
        $wpdb->delete($grants, ['role_key' => 'editor', 'ability_key' => CorexAbility::MANAGE_FORMS]);
        if ($this->editorGrant !== null) {
            $wpdb->insert($grants, $this->editorGrant);
        }
    }
    // Its audit event is written under the administrator against a role the install really has, so
    // only "after this test began" separates it from a change somebody actually made.
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$activity} WHERE id > %d AND kind = %s AND target_type = %s AND target_id = %s",
        $this->lastActivityId,
        'access.role.changed',
        'role',
        'editor',
    ));

    if (! isset($this->requesterId) || $this->requesterId < 1) {
        return;
    }

    // Creating and approving a request are both audited against it, and creating it tells the
    // administrators somebody is waiting. Those rows outlived the request. They are found through
    // the request ids, so before the rows that carry those are deleted.
    $requestIds = $wpdb->get_col($wpdb->prepare(
        'SELECT id FROM ' . $this->migrator->fullName(AccessTables::REQUESTS) . ' WHERE requester_id = %d',
        $this->requesterId,
    ));
    foreach ($requestIds as $requestId) {
        $wpdb->delete($activity, ['target_type' => 'access_request', 'target_id' => (string) $requestId]);
        $wpdb->delete($this->migrator->fullName(NotificationTable::NAME), [
            'source_type' => 'access_request',
            'source_id' => (string) $requestId,
        ]);
    }

    // The request first, then its requester: nothing in CoreX listens for a user being deleted, so
    // `wp_delete_user()` alone leaves the row behind, pointing at nobody. By requester, because
    // this account is the test's own and the table also holds requests that are not.
    $wpdb->delete(
        $this->container->make(\Corex\Database\Schema\Migrator::class)->fullName(AccessTables::REQUESTS),
        ['requester_id' => $this->requesterId],
        ['%d'],
    );

    // Back to nobody first, so the request is not left authenticated as a deleted user.
    wp_set_current_user(0);

    if (! function_exists('wp_delete_user')) {
        require_once ABSPATH . 'wp-admin/includes/user.php';
    }

    wp_delete_user($this->requesterId);
});

it('registers the Access REST routes', function () {
    add_action('rest_api_init', [$this->controller, 'register']);
    do_action('rest_api_init', rest_get_server());

    expect(rest_get_server()->get_routes())->toHaveKeys([
        '/corex/v1/access/catalog',
        '/corex/v1/access/roles/(?P<role>[\w-]+)',
        '/corex/v1/access/roles/(?P<role>[\w-]+)/preview',
        '/corex/v1/access/roles/(?P<role>[\w-]+)/apply',
        '/corex/v1/access/requests',
        '/corex/v1/access/requests/(?P<id>\d+)/decision',
    ]);
});

it('previews and applies a role ability change with a confirmation', function () {
    $previewRequest = accessRequest('POST', '/corex/v1/access/roles/editor/preview', [
        'changes' => [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW],
    ]);
    $previewRequest->set_param('role', 'editor');
    $preview = $this->controller->previewRole($previewRequest);
    $targetHash = $preview->get_data()['data']['target_hash'];
    $confirmation = new Confirmation(
        operationKind: \Corex\Config\Access\AccessService::ROLE_CHANGE_OPERATION,
        targetHash: $targetHash,
        actorId: get_current_user_id(),
        expiresAt: new DateTimeImmutable('+5 minutes'),
    );

    $applyRequest = accessRequest('POST', '/corex/v1/access/roles/editor/apply', [
        'changes' => [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW],
        'confirmation' => [
            'operation_kind' => $confirmation->operationKind,
            'target_hash' => $confirmation->targetHash,
            'actor_id' => $confirmation->actorId,
            'expires_at' => $confirmation->expiresAt->format(DATE_ATOM),
            'required_phrase' => $confirmation->requiredPhrase,
            'used_at' => null,
        ],
    ]);
    $applyRequest->set_param('role', 'editor');
    $applied = $this->controller->applyRole($applyRequest);

    expect($preview->get_status())->toBe(200)
        ->and($preview->get_data()['data']['allowed'])->toBeTrue()
        ->and($applied->get_data()['data']['result']['state'])->toBe('completed')
        ->and($this->container->make(RoleAbilityStore::class)->effectsForRole('editor')[CorexAbility::MANAGE_FORMS])
        ->toBe(AccessPolicy::EFFECT_ALLOW);
});

it('creates and approves an access request without using the protected login route', function () {
    // Its own subscriber, deleted in `afterEach`. This used to borrow the first subscriber the
    // install happened to have — and approving the request below grants that account a real
    // ability, on a developer's real site — and, finding none, to create `corex-access-requester`
    // and leave it there.
    $requesterId = wp_insert_user([
        'user_login' => 'corex-access-requester-' . wp_generate_password(8, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-access-', true) . '@example.test',
        'role'       => 'subscriber',
    ]);

    // Checked rather than cast: `(int)` turns a WP_Error into 1, and `afterEach` deletes the user
    // with this id and every access request that user filed.
    expect($requesterId)->toBeInt();

    $this->requesterId = $requesterId;
    wp_set_current_user($requesterId);

    $create = accessRequest('POST', '/corex/v1/access/requests', [
        'ability' => CorexAbility::MANAGE_FORMS,
        'reason' => 'I need to manage the forms queue.',
    ]);
    $created = $this->controller->createRequest($create);
    $requestId = $created->get_data()['data']['result']['affected_ids'][0];

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($admins[0] ?? 0));
    $decision = accessRequest('POST', '/corex/v1/access/requests/' . $requestId . '/decision', [
        'approved' => true,
        'note' => 'Approved for support rotation.',
    ]);
    $decision->set_param('id', $requestId);
    $decided = $this->controller->decideRequest($decision);
    $stored = $this->container->make(AccessRequestRepository::class)->find((int) $requestId);

    expect($created->get_status())->toBe(200)
        ->and($decided->get_data()['data']['result']['state'])->toBe('completed')
        ->and($stored['state'])->toBe('approved')
        ->and(user_can($requesterId, CorexAbility::MANAGE_FORMS))->toBeTrue();
});
