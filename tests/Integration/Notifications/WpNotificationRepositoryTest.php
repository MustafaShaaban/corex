<?php

/**
 * Integration tests for notification persistence (spec 072 US1/US3: FR-002, FR-003, FR-011).
 *
 * Real WordPress, real tables. Covers dedup-keyed occurrence merging, visibility-filtered reads that
 * never leak to an unauthorized actor, per-user state, the condition lifecycle, and bounded pruning.
 *
 * @package Corex\Tests\Integration\Notifications
 */

declare(strict_types=1);

use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Notifications\NotificationUserStateTable;
use Corex\Config\Notifications\WpNotificationRepository;
use Corex\Database\Schema\Migrator;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationSeverity;

beforeEach(function () {
    global $wpdb;
    $this->migrator = new Migrator();
    $this->migrator->create((new NotificationTable())->schema());
    $this->migrator->create((new NotificationUserStateTable())->schema());
    $this->repo = new WpNotificationRepository($this->migrator);

    // Clean the two tables so tests are independent.
    $wpdb->query('DELETE FROM ' . $this->migrator->fullName(NotificationUserStateTable::NAME));
    $wpdb->query('DELETE FROM ' . $this->migrator->fullName(NotificationTable::NAME));

    $this->allow = static fn (string $ability): bool => true;
    $this->deny  = static fn (string $ability): bool => false;
});

function makeNotification(NotificationRecipient $recipient, string $dedup = 'submission.new:contact'): Notification
{
    return Notification::create(
        type: 'submission.new',
        category: NotificationCategory::SUBMISSIONS,
        severity: NotificationSeverity::ACTION,
        sourceModule: 'forms',
        titleKey: 'notifications.submission.new.title',
        messageKey: 'notifications.submission.new.body',
        rendered: ['title' => 'New submission', 'body' => 'Contact form'],
        dedupKey: $dedup,
        recipient: $recipient,
        occurredAt: new DateTimeImmutable('now'),
    );
}

it('inserts a new notification and finds it back', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7)));

    expect($stored->id)->toBeGreaterThan(0);
    $found = $this->repo->find($stored->id);
    expect($found)->not->toBeNull()
        ->and($found->dedupKey)->toBe('submission.new:contact')
        ->and($found->occurrences)->toBe(1);
});

it('merges a repeat by dedup key into one record with an incremented count', function () {
    $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7)));
    $second = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7)));

    expect($second->occurrences)->toBe(2);
    // Only one row exists for that dedup key.
    global $wpdb;
    $count = (int) $wpdb->get_var('SELECT COUNT(*) FROM ' . $this->migrator->fullName(NotificationTable::NAME));
    expect($count)->toBe(1);
});

it('returns a user-targeted notification to that user only, never to others', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7)));

    $mine = $this->repo->queryForActor(NotificationQuery::fromRequest([]), 7, $this->allow);
    $theirs = $this->repo->queryForActor(NotificationQuery::fromRequest([]), 8, $this->allow);

    expect($mine['total'])->toBe(1)
        ->and($mine['items'][0]['id'])->toBe($stored->id)
        ->and($theirs['total'])->toBe(0);   // FR-003: not visible, not counted
});

it('does not leak an ability-targeted notification to a user lacking the ability', function () {
    $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forAbility('corex_manage_email'), 'mail.provider.failure:default'));

    $holder = $this->repo->queryForActor(NotificationQuery::fromRequest([]), 7, $this->allow);
    $lacker = $this->repo->queryForActor(NotificationQuery::fromRequest([]), 7, $this->deny);

    expect($holder['total'])->toBe(1)
        ->and($lacker['total'])->toBe(0);
});

it('counts only unread, visible notifications for the actor', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7)));

    expect($this->repo->unreadCountForActor(7, $this->allow))->toBe(1)
        ->and($this->repo->unreadCountForActor(8, $this->allow))->toBe(0);

    $this->repo->markRead($stored->id, 7);
    expect($this->repo->unreadCountForActor(7, $this->allow))->toBe(0);
});

it('keeps per-user read state private to each user', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUsers([7, 8])));

    $this->repo->markRead($stored->id, 7);

    expect($this->repo->unreadCountForActor(7, $this->allow))->toBe(0)  // 7 read it
        ->and($this->repo->unreadCountForActor(8, $this->allow))->toBe(1); // 8 still unread
});

it('refuses to mark read a notification the actor cannot see', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7)));

    expect($this->repo->markRead($stored->id, 8))->toBeFalse(); // not their notification
    expect($this->repo->unreadCountForActor(7, $this->allow))->toBe(1); // unchanged
});

it('resolves and reopens a condition by dedup key, independent of user dismissal', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forAbility('corex_manage_operations'), 'readiness.blocker:https'));
    $this->repo->dismiss($stored->id, 7); // one user hides it

    $resolvedCount = $this->repo->resolveByDedupKey('readiness.blocker:https', 'HTTPS is now configured.', new DateTimeImmutable('now'));
    expect($resolvedCount)->toBe(1)
        ->and($this->repo->find($stored->id)->isResolved())->toBeTrue();

    // The condition recurs: a fresh occurrence reopens it.
    $reopened = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forAbility('corex_manage_operations'), 'readiness.blocker:https'));
    expect($reopened->isResolved())->toBeFalse()
        ->and($reopened->occurrences)->toBe(2);
});

it('prunes resolved notifications older than the cutoff, in bounded batches', function () {
    global $wpdb;
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7), 'old.thing:1'));
    // Backdate + resolve it well before the cutoff.
    $wpdb->update(
        $this->migrator->fullName(NotificationTable::NAME),
        ['resolved_at' => '2020-01-01 00:00:00', 'latest_occurred_at' => '2020-01-01 00:00:00'],
        ['id' => $stored->id],
    );

    $removed = $this->repo->pruneOlderThan(new DateTimeImmutable('2021-01-01'), 500);
    expect($removed)->toBe(1)
        ->and($this->repo->find($stored->id))->toBeNull();
});
