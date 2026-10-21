<?php

/**
 * Approving a request, the grants it makes, revoking one and the daily end of an expired one,
 * through the real REST server and real WordPress accounts (spec 112, User Story 1, FR-002 to
 * FR-008).
 *
 * Every account is the test's own and deleted afterwards with what it filed, and every grant is
 * to that account: nothing here gives an ability to a person of the install. Mail is refused by a
 * `pre_wp_mail` filter, because a decision mails the requester where a route exists.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\AccessRequestStore;
use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Access\AccessGrantScheduler;
use Corex\Config\Access\AccessTables;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Notifications\NotificationTable;
use Corex\Database\Schema\Migrator;

function grantsTestUser(string $role): int
{
    $id = wp_insert_user([
        'user_login' => 'corex-grants-' . $role . '-' . wp_generate_password(8, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-grants-', true) . '@example.test',
        'role'       => $role,
    ]);
    expect($id)->toBeInt();

    return $id;
}

/** @param array<string,mixed> $body */
function grantsRoute(string $method, string $route, array $body = []): WP_REST_Response
{
    $request = new WP_REST_Request($method, '/corex/v1/access/' . $route);
    $request->set_header('X-WP-Nonce', wp_create_nonce('wp_rest'));
    if ($body !== []) {
        $request->set_body_params($body);
    }

    return rest_do_request($request);
}

beforeEach(function () {
    $container = Boot::app()->container();
    $this->migrator = $container->make(Migrator::class);
    foreach ($container->make(AccessTables::class)->schemas() as $schema) {
        $this->migrator->create($schema);
    }
    $this->store = $container->make(AccessRequestStore::class);
    $this->scheduler = $container->make(AccessGrantScheduler::class);

    add_filter('pre_wp_mail', '__return_true');
    $this->requesterId = grantsTestUser('subscriber');
    $this->approverId  = grantsTestUser('administrator');

    // Files a request as the requester and returns its id, leaving the approver signed in.
    $this->file = function (array $target): int {
        wp_set_current_user($this->requesterId);
        $created = grantsRoute('POST', 'requests', $target + ['reason' => 'Needed for my work.']);
        wp_set_current_user($this->approverId);

        return (int) $created->get_data()['data']['result']['affected_ids'][0];
    };
    $this->preview = fn (int $id): array => grantsRoute('POST', "requests/{$id}/preview")->get_data()['data']['preview'];
    $this->approve = function (int $id, array $overrides = [], ?string $typed = null): array {
        $preview = ($this->preview)($id);
        $confirmation = [
            'operation_kind' => 'access.request.approve',
            'target_hash'    => $preview['target_hash'],
            'actor_id'       => $this->approverId,
            'expires_at'     => gmdate('c', time() + 600),
        ];
        if ($typed !== null) {
            $confirmation['required_phrase'] = $typed;
        }

        return grantsRoute('POST', "requests/{$id}/decision", $overrides + [
            'approved'     => true,
            'note'         => 'Covering this month.',
            'expiry_days'  => 7,
            'confirmation' => $confirmation,
        ])->get_data()['data']['result'];
    };
    $this->mine = fn (): array => array_values(array_filter(
        grantsRoute('GET', 'grants')->get_data()['data']['grants'],
        fn (array $grant): bool => $grant['person']['id'] === $this->requesterId,
    ));
});

afterEach(function () {
    remove_filter('pre_wp_mail', '__return_true');

    global $wpdb;
    $requests = $this->migrator->fullName(AccessTables::REQUESTS);
    foreach ($wpdb->get_col($wpdb->prepare("SELECT id FROM {$requests} WHERE requester_id = %d", $this->requesterId)) as $id) {
        $wpdb->delete($this->migrator->fullName(ActivityTable::NAME), ['target_type' => 'access_request', 'target_id' => (string) $id]);
        $wpdb->delete($this->migrator->fullName(NotificationTable::NAME), ['source_type' => 'access_request', 'source_id' => (string) $id]);
    }
    $wpdb->delete($requests, ['requester_id' => $this->requesterId], ['%d']);

    wp_set_current_user(0);
    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->requesterId);
    wp_delete_user($this->approverId);
});

it('shows the approver what is granted, its risk, the reason, and the name to type', function () {
    $id = ($this->file)(['area' => CorexAbility::GROUP_DATA]);

    $preview = ($this->preview)($id);

    expect($preview['requester']['id'])->toBe($this->requesterId)
        ->and($preview['reason'])->toBe('Needed for my work.')
        ->and($preview['area'])->toBe(CorexAbility::GROUP_DATA)
        ->and(array_column($preview['abilities'], 'risk', 'key'))->toBe([
            CorexAbility::MANAGE_DATA          => CorexAbility::RISK_DANGEROUS,
            CorexAbility::EXPORT_PERSONAL_DATA => CorexAbility::RISK_SENSITIVE,
        ])
        ->and($preview['requires_typed_name'])->toBeTrue()
        ->and($preview['typed_name'])->toBe(CorexAbility::MANAGE_DATA)
        ->and($preview['expiry_choices'])->toBe([7, 30, 90])
        ->and($preview['expiry_default'])->toBe(30);
});

it('refuses an approval without a note, with a wrong typed name, or with an expiry it does not offer, and grants nothing', function (array $overrides, ?string $typed, string $code) {
    $id = ($this->file)(['ability' => CorexAbility::MANAGE_DATA]);

    $result = ($this->approve)($id, $overrides, $typed);

    expect($result['state'])->toBe('blocked')
        ->and(array_column($result['errors'], 'code'))->toBe([$code])
        ->and(user_can($this->requesterId, CorexAbility::MANAGE_DATA))->toBeFalse()
        ->and($this->store->find($id)['state'])->toBe('pending');
})->with([
    'no note'        => [['note' => ''], CorexAbility::MANAGE_DATA, 'note_required'],
    'a wrong name'   => [[], CorexAbility::MANAGE_FORMS, 'confirmation_invalid'],
    'no name typed'  => [[], null, 'confirmation_invalid'],
    'fourteen days'  => [['expiry_days' => 14], CorexAbility::MANAGE_DATA, 'expiry_invalid'],
]);

it('grants with a note and an expiry, lists the grant, and revoking it takes the ability back', function () {
    $id = ($this->file)(['ability' => CorexAbility::MANAGE_DATA]);

    $approved = ($this->approve)($id, [], CorexAbility::MANAGE_DATA);
    $listed   = ($this->mine)();
    $granted  = user_can($this->requesterId, CorexAbility::MANAGE_DATA);
    $revoked  = grantsRoute('POST', "grants/{$id}/revoke")->get_data()['data']['result'];

    expect($approved['state'])->toBe('completed')
        ->and($granted)->toBeTrue()
        ->and($listed)->toHaveCount(1)
        ->and($listed[0]['approver']['id'])->toBe($this->approverId)
        ->and($listed[0]['note'])->toBe('Covering this month.')
        ->and($listed[0]['expires_at'])->not->toBeNull()
        ->and($listed[0]['in_effect'])->toBeTrue()
        ->and($revoked['state'])->toBe('completed')
        ->and(user_can($this->requesterId, CorexAbility::MANAGE_DATA))->toBeFalse()
        ->and(($this->mine)())->toBe([])
        ->and(grantsRoute('POST', "grants/{$id}/revoke")->get_data()['data']['result']['state'])->toBe('blocked');
});

it('lists a grant approved before expiries existed as having none, and keeps it', function () {
    $id = ($this->file)(['ability' => CorexAbility::MANAGE_FORMS]);
    $this->store->transition($id, 'approved', $this->approverId, 'Approved before spec 112.', new DateTimeImmutable('-60 days'));
    (new WP_User($this->requesterId))->add_cap(CorexAbility::MANAGE_FORMS, true);

    $this->scheduler->run();
    $listed = ($this->mine)();

    expect($listed)->toHaveCount(1)
        ->and($listed[0]['expires_at'])->toBeNull()
        ->and(user_can($this->requesterId, CorexAbility::MANAGE_FORMS))->toBeTrue();
});

it('says a grant is not in effect when its ability was taken away outside CoreX', function () {
    $id = ($this->file)(['ability' => CorexAbility::MANAGE_FORMS]);
    ($this->approve)($id);
    (new WP_User($this->requesterId))->remove_cap(CorexAbility::MANAGE_FORMS);

    $listed = ($this->mine)();

    expect($listed[0]['in_effect'])->toBeFalse()
        ->and($listed[0]['state_note'])->toBe('removed_outside_corex');
});

it('does not list a grant to an account that no longer exists', function () {
    $id = ($this->file)(['ability' => CorexAbility::MANAGE_FORMS]);
    ($this->approve)($id);
    $listedBefore = ($this->mine)();

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->requesterId);

    expect($listedBefore)->toHaveCount(1)
        ->and(($this->mine)())->toBe([]);
});

it('keeps an ability another standing grant still gives when one grant is revoked', function () {
    $ability = ($this->file)(['ability' => CorexAbility::MANAGE_DATA]);
    ($this->approve)($ability, [], CorexAbility::MANAGE_DATA);
    $area = ($this->file)(['area' => CorexAbility::GROUP_DATA]);
    ($this->approve)($area, [], CorexAbility::MANAGE_DATA);

    grantsRoute('POST', "grants/{$ability}/revoke");

    expect(user_can($this->requesterId, CorexAbility::MANAGE_DATA))->toBeTrue()
        ->and(user_can($this->requesterId, CorexAbility::EXPORT_PERSONAL_DATA))->toBeTrue();
});

it('ends an expired grant each day, takes the ability back, records it and tells both people', function () {
    $id = ($this->file)(['ability' => CorexAbility::MANAGE_FORMS]);
    ($this->approve)($id);
    global $wpdb;
    $wpdb->update($this->migrator->fullName(AccessTables::REQUESTS), ['grant_expires_at' => gmdate('Y-m-d H:i:s', time() - 60)], ['id' => $id]);

    $this->scheduler->run();

    $kinds = $wpdb->get_col($wpdb->prepare(
        'SELECT kind FROM ' . $this->migrator->fullName(ActivityTable::NAME) . ' WHERE target_type = %s AND target_id = %s',
        'access_request',
        (string) $id,
    ));
    $recipients = $wpdb->get_col($wpdb->prepare(
        'SELECT recipient_json FROM ' . $this->migrator->fullName(NotificationTable::NAME) . ' WHERE source_type = %s AND source_id = %s AND type = %s',
        'access_request',
        (string) $id,
        'access.grant.expired',
    ));
    $told = array_merge(...array_map(
        static fn (string $json): array => array_map('intval', json_decode($json, true)['user_ids'] ?? []),
        $recipients,
    ));

    expect(user_can($this->requesterId, CorexAbility::MANAGE_FORMS))->toBeFalse()
        ->and($this->store->find($id)['endedReason'])->toBe(AccessRequestStore::ENDED_EXPIRED)
        ->and($kinds)->toContain('access.grant.expired')
        ->and($told)->toEqualCanonicalizing([$this->requesterId, $this->approverId]);
});

it('cancels a pending request for an ability a request may no longer ask for', function () {
    global $wpdb;
    // Filed before spec 112, when a critical ability could be asked for: written as it was stored.
    $wpdb->insert($this->migrator->fullName(AccessTables::REQUESTS), [
        'requester_id' => $this->requesterId,
        'ability_key'  => CorexAbility::MANAGE_ACCESS,
        'reason'       => 'Old request.',
        'state'        => 'pending',
        'created_at'   => gmdate('Y-m-d H:i:s'),
        'expires_at'   => gmdate('Y-m-d H:i:s', time() + DAY_IN_SECONDS),
    ]);
    $id = (int) $wpdb->insert_id;

    $this->scheduler->run();

    expect($this->store->find($id)['state'])->toBe('cancelled')
        ->and($this->store->find($id)['cancelReason'])->toBe(AccessRequestStore::CANCELLED_NOT_REQUESTABLE);
});

it('keeps the grants list and revoking to administrators', function () {
    wp_set_current_user($this->requesterId);

    expect(grantsRoute('GET', 'grants')->get_status())->toBe(403)
        ->and(grantsRoute('POST', 'grants/1/revoke')->get_status())->toBe(403)
        ->and(grantsRoute('POST', 'requests/1/preview')->get_status())->toBe(403);
});
