<?php

/**
 * A grant made by approving a request: kept with its expiry, listed while it stands, ended once,
 * and found when its expiry has passed (spec 112, User Story 1, FR-005 to FR-008).
 *
 * Against the real table, created by the same schema the migration runner applies (foundation
 * schema 4), so the columns themselves are part of what is proved.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\AccessRequestStore;
use Corex\Access\CorexAbility;
use Corex\Boot;
use Corex\Config\Access\AccessTables;
use Corex\Database\Schema\Migrator;

beforeEach(function () {
    $container = Boot::app()->container();
    $this->migrator = $container->make(Migrator::class);
    foreach ($container->make(AccessTables::class)->schemas() as $schema) {
        $this->migrator->create($schema);
    }

    $this->store = $container->make(AccessRequestStore::class);
    $this->table = $this->migrator->fullName(AccessTables::REQUESTS);

    // Nobody's id: the store never looks the person up, and deleting by it cannot reach a real row.
    $this->requesterId = random_int(900_000_000, 999_999_999);
    $this->reviewerId  = 1;
    $this->now         = new DateTimeImmutable('2026-10-21 10:00:00', new DateTimeZone('UTC'));

    $this->pending = fn (string $ability = CorexAbility::MANAGE_FORMS): int => (int) $this->store->createUnlessPending(
        $this->requesterId,
        $ability,
        null,
        'Needed for my work.',
        $this->now,
        $this->now->modify('+7 days'),
    );
    $this->mine = fn (array $rows): array => array_values(array_filter(
        $rows,
        fn (array $row): bool => $row['requesterId'] === $this->requesterId,
    ));
});

afterEach(function () {
    global $wpdb;

    $wpdb->delete($this->table, ['requester_id' => $this->requesterId], ['%d']);
});

it('adds the grant columns to the access requests table', function () {
    global $wpdb;

    $columns = $wpdb->get_col('SHOW COLUMNS FROM ' . $this->table);

    expect($columns)->toContain('grant_expires_at', 'ended_at', 'ended_by', 'ended_reason', 'cancel_reason');
});

it('keeps an approved grant with its expiry and lists it while it stands', function () {
    $id = ($this->pending)();

    $approved = $this->store->approve($id, $this->reviewerId, 'Covering forms this month.', $this->now, $this->now->modify('+30 days'));
    $grant    = ($this->mine)($this->store->grants())[0] ?? null;

    expect($approved)->toBeTrue()
        ->and($grant['id'])->toBe($id)
        ->and($grant['state'])->toBe('approved')
        ->and($grant['reviewNote'])->toBe('Covering forms this month.')
        ->and($grant['grantExpiresAt']->format('Y-m-d H:i:s'))->toBe('2026-11-20 10:00:00')
        ->and($grant['endedAt'])->toBeNull();
});

it('lists a grant approved before expiries existed, with no expiry', function () {
    $id = ($this->pending)();
    $this->store->transition($id, 'approved', $this->reviewerId, 'Approved before spec 112.', $this->now);

    $grant = ($this->mine)($this->store->grants())[0] ?? null;

    expect($grant['id'])->toBe($id)
        ->and($grant['grantExpiresAt'])->toBeNull();
});

it('approves only a pending request', function () {
    $id = ($this->pending)();
    $this->store->transition($id, 'denied', $this->reviewerId, 'No.', $this->now);

    expect($this->store->approve($id, $this->reviewerId, 'Yes.', $this->now, $this->now->modify('+7 days')))->toBeFalse()
        ->and(($this->mine)($this->store->grants()))->toBe([]);
});

it('ends a grant once, with who ended it and why, and stops listing it', function () {
    $id = ($this->pending)();
    $this->store->approve($id, $this->reviewerId, 'Covering.', $this->now, $this->now->modify('+30 days'));

    $first  = $this->store->endGrant($id, AccessRequestStore::ENDED_REVOKED, $this->reviewerId, $this->now->modify('+1 day'));
    $second = $this->store->endGrant($id, AccessRequestStore::ENDED_EXPIRED, null, $this->now->modify('+2 days'));
    $ended  = $this->store->find($id);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($ended['endedReason'])->toBe(AccessRequestStore::ENDED_REVOKED)
        ->and($ended['endedBy'])->toBe($this->reviewerId)
        ->and($ended['endedAt']->format('Y-m-d'))->toBe('2026-10-22')
        ->and(($this->mine)($this->store->grants()))->toBe([]);
});

it('finds the grants whose expiry has passed, and no other', function () {
    $passed    = ($this->pending)(CorexAbility::MANAGE_FORMS);
    $standing  = ($this->pending)(CorexAbility::MANAGE_EMAIL);
    $noExpiry  = ($this->pending)(CorexAbility::MANAGE_BLOG);
    $ended     = ($this->pending)(CorexAbility::MANAGE_SETTINGS);
    $this->store->approve($passed, $this->reviewerId, 'a', $this->now, $this->now->modify('+7 days'));
    $this->store->approve($standing, $this->reviewerId, 'b', $this->now, $this->now->modify('+30 days'));
    $this->store->transition($noExpiry, 'approved', $this->reviewerId, 'c', $this->now);
    $this->store->approve($ended, $this->reviewerId, 'd', $this->now, $this->now->modify('+7 days'));
    $this->store->endGrant($ended, AccessRequestStore::ENDED_REVOKED, $this->reviewerId, $this->now);

    $found = array_column(($this->mine)($this->store->expiredGrants($this->now->modify('+8 days'))), 'id');

    expect($found)->toBe([$passed]);
});

it('cancels a pending request with its reason, once', function () {
    $id = ($this->pending)();

    $first     = $this->store->cancel($id, AccessRequestStore::CANCELLED_NOT_REQUESTABLE, $this->now);
    $second    = $this->store->cancel($id, AccessRequestStore::CANCELLED_NOT_REQUESTABLE, $this->now);
    $cancelled = $this->store->find($id);

    expect($first)->toBeTrue()
        ->and($second)->toBeFalse()
        ->and($cancelled['state'])->toBe('cancelled')
        ->and($cancelled['cancelReason'])->toBe(AccessRequestStore::CANCELLED_NOT_REQUESTABLE)
        ->and($this->store->pendingFor($this->requesterId, CorexAbility::MANAGE_FORMS, null))->toBeNull();
});
