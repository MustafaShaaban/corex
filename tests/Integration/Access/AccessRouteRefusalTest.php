<?php

/**
 * What the Access REST routes answer for a request they cannot read.
 *
 * Each of these was answered 500, "There has been a critical error on this website", with a fatal
 * error in the PHP log (measured over HTTP on 2026-10-13, DECISIONS #347): the service threw and
 * the route did not catch it. They go through
 * WordPress's REST server and not to the controller's methods, because the status and the body
 * that server makes of the controller's answer are what a script reads.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\AccessPolicy;
use Corex\Access\CorexAbility;
use Corex\Access\InvalidAccessInput;
use Corex\Access\RoleAbilityStore;
use Corex\Boot;
use Corex\Config\Access\AccessService;
use Corex\Config\Access\AccessTables;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Notifications\NotificationTable;
use Corex\Database\Schema\Migrator;

/** A role no install has, so a change this file fails to refuse lands on nobody. */
const REFUSAL_TEST_ROLE = 'corex_refusal_test_role';

/**
 * Sends a JSON body to an Access route as the current user, with that user's REST nonce.
 *
 * @param array<string,mixed> $body
 */
function accessRoute(string $method, string $route, array $body = []): WP_REST_Response
{
    $request = new WP_REST_Request($method, '/corex/v1/access/' . $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));

    if ($method === 'GET') {
        $request->set_query_params($body);
    } else {
        $request->set_header('Content-Type', 'application/json');
        $request->set_body((string) wp_json_encode($body));
    }

    return rest_get_server()->dispatch($request);
}

/**
 * The parameter a 400 names and the code it gives for it, or the status when it is not a 400.
 *
 * @return array{string,string}|int
 */
function refusedParameter(WP_REST_Response $response): array|int
{
    $body = $response->get_data();

    if ($response->get_status() !== 400 || ($body['code'] ?? null) !== 'rest_invalid_param') {
        return $response->get_status();
    }

    $parameter = (string) array_key_first($body['data']['params']);

    return [$parameter, $body['data']['details'][$parameter]['code']];
}

beforeEach(function () {
    // A site with an email route for a new request mails its administrators when one is stored.
    add_filter('pre_wp_mail', '__return_true');

    $container      = Boot::app()->container();
    $this->migrator = $container->make(Migrator::class);
    $this->roles    = $container->make(RoleAbilityStore::class);
    foreach ($container->make(AccessTables::class)->schemas() as $schema) {
        $this->migrator->create($schema);
    }

    $admins        = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->adminId = (int) ($admins[0] ?? 0);

    // The requester is this file's own, so "nothing was stored for them" can be counted on an
    // install whose tables hold other people's requests.
    $requesterId = wp_insert_user([
        'user_login' => 'corex-refusal-' . wp_generate_password(8, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-refusal-', true) . '@example.test',
        'role'       => 'subscriber',
    ]);
    expect($requesterId)->toBeInt();
    $this->requesterId = $requesterId;

    global $wpdb;
    $this->newestNotification = (int) $wpdb->get_var('SELECT MAX(id) FROM ' . $this->migrator->fullName(NotificationTable::NAME));

    /** How many rows of a table are this requester's. */
    $this->stored = function (string $table, string $column): int {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . $this->migrator->fullName($table) . " WHERE {$column} = %s",
            (string) $this->requesterId,
        ));
    };
});

afterEach(function () {
    remove_filter('pre_wp_mail', '__return_true');

    // What a test that failed to refuse left behind: the request, what was written about it, and
    // a grant on the role nobody has.
    global $wpdb;
    $requests = $this->migrator->fullName(AccessTables::REQUESTS);
    foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$requests} WHERE requester_id = %d", $this->requesterId)) as $requestId) {
        $wpdb->delete($this->migrator->fullName(ActivityTable::NAME), ['target_type' => 'access_request', 'target_id' => (string) $requestId]);
        $wpdb->delete($this->migrator->fullName(NotificationTable::NAME), ['source_type' => 'access_request', 'source_id' => (string) $requestId]);
    }
    $wpdb->delete($requests, ['requester_id' => $this->requesterId], ['%d']);
    $wpdb->delete($this->migrator->fullName(AccessTables::ROLE_GRANTS), ['role_key' => REFUSAL_TEST_ROLE]);
    $wpdb->delete($this->migrator->fullName(ActivityTable::NAME), ['target_type' => 'role', 'target_id' => REFUSAL_TEST_ROLE]);

    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->requesterId);
});

it('answers 400 for an access request it cannot read, names the parameter, and stores nothing', function (array $body, string $parameter, string $refusal) {
    wp_set_current_user($this->requesterId);

    $response = accessRoute('POST', 'requests', $body);

    global $wpdb;
    $notified = (int) $wpdb->get_var($wpdb->prepare(
        'SELECT COUNT(*) FROM ' . $this->migrator->fullName(NotificationTable::NAME) . ' WHERE id > %d AND source_type = %s',
        $this->newestNotification,
        'access_request',
    ));

    expect(refusedParameter($response))->toBe([$parameter, $refusal])
        // The words are for a person; what they say is held by the code beside them.
        ->and($response->get_data()['message'])->toBeString()->not->toBe('')
        ->and($response->get_data()['data']['params'][$parameter])->toBe($response->get_data()['message'])
        ->and(($this->stored)(AccessTables::REQUESTS, 'requester_id'))->toBe(0)
        ->and(($this->stored)(ActivityTable::NAME, 'actor_id'))->toBe(0)
        ->and($notified)->toBe(0);
})->with([
    'an empty reason'                => [['ability' => CorexAbility::MANAGE_FORMS, 'reason' => ''], 'reason', InvalidAccessInput::REASON_REQUIRED],
    'no reason'                      => [['ability' => CorexAbility::MANAGE_FORMS], 'reason', InvalidAccessInput::REASON_REQUIRED],
    // Read as text, a list is the word "Array", and that was stored as the reason.
    'a reason that is a list'        => [['ability' => CorexAbility::MANAGE_FORMS, 'reason' => ['a', 'b']], 'reason', InvalidAccessInput::REASON_REQUIRED],
    'a reason one character over'    => [['ability' => CorexAbility::MANAGE_FORMS, 'reason' => str_repeat('a', AccessService::MAX_REASON_LENGTH + 1)], 'reason', InvalidAccessInput::REASON_TOO_LONG],
    'an ability CoreX does not have' => [['ability' => 'corex_no_such_ability', 'reason' => 'I maintain the forms.'], 'ability', InvalidAccessInput::ABILITY_UNKNOWN],
    'an area CoreX does not have'    => [['area' => 'forms', 'reason' => 'I maintain the forms.'], 'area', InvalidAccessInput::AREA_UNKNOWN],
    'an ability and an area'         => [['ability' => CorexAbility::MANAGE_FORMS, 'area' => 'forms-flows', 'reason' => 'I maintain the forms.'], 'area', InvalidAccessInput::TARGET_AMBIGUOUS],
    'neither an ability nor an area' => [['reason' => 'I maintain the forms.'], 'ability', InvalidAccessInput::TARGET_REQUIRED],
    'nothing at all'                 => [[], 'reason', InvalidAccessInput::REASON_REQUIRED],
]);

it('takes a reason of exactly the longest length', function () {
    wp_set_current_user($this->requesterId);

    $response = accessRoute('POST', 'requests', [
        'ability' => CorexAbility::MANAGE_FORMS,
        'reason'  => str_repeat('a', AccessService::MAX_REASON_LENGTH),
    ]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['result']['state'])->toBe('completed')
        ->and(($this->stored)(AccessTables::REQUESTS, 'requester_id'))->toBe(1);
});

it('answers 400 for a role change it cannot read, at the preview and when applying, and changes nothing', function (array $body, string $parameter, string $refusal) {
    wp_set_current_user($this->adminId);

    // A confirmation that would be taken, so that applying is refused for the body's own fault.
    $confirmed = $body + ['confirmation' => [
        'operation_kind' => AccessService::ROLE_CHANGE_OPERATION,
        'target_hash'    => str_repeat('a', 64),
        'actor_id'       => $this->adminId,
        'expires_at'     => gmdate(DATE_ATOM, time() + 300),
    ]];

    expect(refusedParameter(accessRoute('POST', 'roles/' . REFUSAL_TEST_ROLE . '/preview', $body)))->toBe([$parameter, $refusal])
        ->and(refusedParameter(accessRoute('POST', 'roles/' . REFUSAL_TEST_ROLE . '/apply', $confirmed)))->toBe([$parameter, $refusal])
        ->and($this->roles->effectsForRole(REFUSAL_TEST_ROLE))->toBe([]);
})->with([
    'an ability CoreX does not have' => [['changes' => ['corex_no_such_ability' => AccessPolicy::EFFECT_ALLOW]], 'changes', InvalidAccessInput::ABILITY_UNKNOWN],
    'a key that is a number'         => [['changes' => ['123' => AccessPolicy::EFFECT_ALLOW]], 'changes', InvalidAccessInput::ABILITY_UNKNOWN],
    'a list where a map was meant'   => [['changes' => [AccessPolicy::EFFECT_ALLOW]], 'changes', InvalidAccessInput::ABILITY_UNKNOWN],
    'an effect CoreX does not have'  => [['changes' => [CorexAbility::MANAGE_FORMS => 'maybe']], 'changes', InvalidAccessInput::EFFECT_UNSUPPORTED],
    'an effect that is a list'       => [['changes' => [CorexAbility::MANAGE_FORMS => [AccessPolicy::EFFECT_ALLOW]]], 'changes', InvalidAccessInput::EFFECT_UNSUPPORTED],
    // A parameter of the body is read before the one in the address, as WordPress reads them.
    'a role key that is not one'     => [['role' => '!!!', 'changes' => []], 'role', InvalidAccessInput::ROLE_INVALID],
    'a role key that is a list'      => [['role' => ['editor'], 'changes' => []], 'role', InvalidAccessInput::ROLE_INVALID],
]);

it('answers 400 for a role key that is not one, where a role is read', function () {
    wp_set_current_user($this->adminId);

    expect(refusedParameter(accessRoute('GET', 'roles/' . REFUSAL_TEST_ROLE, ['role' => '!!!'])))
        ->toBe(['role', InvalidAccessInput::ROLE_INVALID]);
});

it('answers 400 for a confirmation it cannot read, and changes nothing', function (array $confirmation) {
    wp_set_current_user($this->adminId);

    $changes  = [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW];
    $response = accessRoute('POST', 'roles/' . REFUSAL_TEST_ROLE . '/apply', ['changes' => $changes] + $confirmation);

    expect(refusedParameter($response))->toBe(['confirmation', InvalidAccessInput::CONFIRMATION_MALFORMED])
        ->and($this->roles->effectsForRole(REFUSAL_TEST_ROLE))->toBe([]);
})->with(function (): array {
    $taken = [
        'operation_kind' => AccessService::ROLE_CHANGE_OPERATION,
        'target_hash'    => str_repeat('a', 64),
        'actor_id'       => 1,
        'expires_at'     => '2099-01-01T00:00:00+00:00',
    ];

    return [
        'none'                           => [[]],
        'one that is not a map'          => [['confirmation' => 'yes']],
        'no kind of operation'           => [['confirmation' => ['operation_kind' => ''] + $taken]],
        'a hash that is not one'         => [['confirmation' => ['target_hash' => 'xyz'] + $taken]],
        'nobody confirming'              => [['confirmation' => ['actor_id' => 0] + $taken]],
        'an expiry that is not a time'   => [['confirmation' => ['expires_at' => 'not a date'] + $taken]],
        'a use that is not a time'       => [['confirmation' => $taken + ['used_at' => 'not a date']]],
    ];
});

it('still answers 200 and blocked for a confirmation it can read that confirms something else', function () {
    wp_set_current_user($this->adminId);

    $response = accessRoute('POST', 'roles/' . REFUSAL_TEST_ROLE . '/apply', [
        'changes'      => [CorexAbility::MANAGE_FORMS => AccessPolicy::EFFECT_ALLOW],
        'confirmation' => [
            'operation_kind' => AccessService::ROLE_CHANGE_OPERATION,
            'target_hash'    => str_repeat('a', 64),
            'actor_id'       => $this->adminId,
            'expires_at'     => gmdate(DATE_ATOM, time() + 300),
        ],
    ]);

    expect($response->get_status())->toBe(200)
        ->and($response->get_data()['data']['result']['state'])->toBe('blocked')
        ->and(array_column($response->get_data()['data']['result']['errors'], 'code'))->toBe(['confirmation_invalid'])
        ->and($this->roles->effectsForRole(REFUSAL_TEST_ROLE))->toBe([]);
});
