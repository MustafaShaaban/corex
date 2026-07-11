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
use Corex\Operations\Confirmation;

function accessRequest(string $method, string $route, array $payload = []): WP_REST_Request
{
    $request = new WP_REST_Request($method, $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    $method === 'GET' ? $request->set_query_params($payload) : $request->set_body_params($payload);

    return $request;
}

beforeEach(function () {
    $this->container = Boot::app()->container();
    foreach ($this->container->make(AccessTables::class)->schemas() as $schema) {
        $this->container->make(\Corex\Database\Schema\Migrator::class)->create($schema);
    }

    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($admins[0] ?? 0));
    $this->controller = $this->container->make(AccessController::class);
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
    $users = get_users(['role' => 'subscriber', 'number' => 1, 'fields' => 'ID']);
    $requesterId = (int) ($users[0] ?? wp_create_user('corex-access-requester', wp_generate_password(), 'requester@example.test'));
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
