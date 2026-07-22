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

it('filters by the per-user status instead of ignoring the filter', function () {
    // NotificationQuery::$status was accepted at the REST boundary, validated, and then never used
    // by any read — `?status=read` returned everything with a 200. These assertions fail if the
    // filter is dropped again.
    $unread = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7), 'status.unread:1'));
    $read   = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7), 'status.read:1'));
    $this->repo->markRead((int) $read->id, 7);

    $readOnly = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'read']),
        7,
        $this->allow,
    );
    $unreadOnly = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'unread']),
        7,
        $this->allow,
    );

    expect($readOnly['total'])->toBe(1)
        ->and($readOnly['items'][0]['id'])->toBe($read->id)
        ->and($unreadOnly['total'])->toBe(1)
        ->and($unreadOnly['items'][0]['id'])->toBe($unread->id)
        // The derived status travels with each item so consumers need not re-derive it.
        ->and($readOnly['items'][0]['user_state']['status'])->toBe('read')
        ->and($unreadOnly['items'][0]['user_state']['status'])->toBe('unread');
});

it('reports a resolved condition as resolved even for a user who never read it', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7), 'status.resolved:1'));
    $this->repo->resolveByDedupKey('status.resolved:1', 'condition cleared', new DateTimeImmutable('now'));

    $resolved = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'resolved']),
        7,
        $this->allow,
    );

    expect($resolved['total'])->toBe(1)
        ->and($resolved['items'][0]['id'])->toBe($stored->id);
});

it('narrows "assigned to me" to notifications that name the actor, not everything they can see', function () {
    // Both are visible to user 7 (who holds every ability here); only one is theirs personally.
    $mine = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7), 'assigned.mine:1'));
    $this->repo->upsertByDedupKey(
        makeNotification(NotificationRecipient::forAbility('corex_manage_submissions'), 'assigned.broadcast:1')
    );

    $all      = $this->repo->queryForActor(NotificationQuery::fromRequest([]), 7, $this->allow);
    $assigned = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['assigned_to_me' => true]),
        7,
        $this->allow,
    );

    expect($all['total'])->toBe(2)
        ->and($assigned['total'])->toBe(1)
        ->and($assigned['items'][0]['id'])->toBe($mine->id);
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

it('agrees with the unread view: a snoozed notification is neither counted nor listed', function () {
    // The bell badge and the "Requires attention" list must not disagree. The count used to exclude
    // only read/dismissed while the list derives a full status, so a snoozed item was counted but
    // not listed — a badge promising items the screen would not show, and an optional widget that
    // could register on the count and then render "all caught up".
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7), 'snoozed.count:1'));
    $this->repo->snooze((int) $stored->id, 7, new DateTimeImmutable('+1 day'));

    $listed = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'unread']),
        7,
        $this->allow,
    );

    expect($this->repo->unreadCountForActor(7, $this->allow))->toBe(0)
        ->and($listed['total'])->toBe(0);
});

it('marks all read without trampling a snooze the user deliberately set', function () {
    // "Mark all as read" is offered from surfaces that show only unread items. Marking a snoozed
    // item read would silently cancel its resurfacing — the user asked to be reminded later, not to
    // have it filed away — so the sweep must touch only what is actually unread.
    $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7), 'sweep.unread:1'));
    $snoozed = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser(7), 'sweep.snoozed:1'));
    $this->repo->snooze((int) $snoozed->id, 7, new DateTimeImmutable('+1 day'));

    $marked = $this->repo->markAllVisibleRead(7, $this->allow);

    $stillSnoozed = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'snoozed']),
        7,
        $this->allow,
    );

    expect($marked)->toBe(1)                                            // only the unread one
        ->and($this->repo->unreadCountForActor(7, $this->allow))->toBe(0) // badge still clears
        ->and($stillSnoozed['total'])->toBe(1);                          // snooze survives
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
