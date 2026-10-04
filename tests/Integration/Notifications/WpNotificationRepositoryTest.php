<?php

/**
 * Integration tests for notification persistence (spec 072 US1/US3: FR-002, FR-003, FR-011).
 *
 * Real WordPress, real tables. Covers dedup-keyed occurrence merging, visibility-filtered reads that
 * never leak to an unauthorized actor, per-user state, the condition lifecycle, and bounded pruning.
 *
 * **The tables are a developer's, and these tests leave them as they found them.** Every read here
 * is scoped by who is asking, so the tests ask as actors nothing else on the install addresses:
 * two user ids no account has, and an ability no producer targets. What they see is then exactly
 * what the test stored, however much the table already holds — which is what the assertions used
 * to get by deleting every row in both tables before each test.
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
use Corex\Tests\Support\NotificationRows;

/** Every dedup key this file stores starts with this, which is how its rows are found again. */
const REPOSITORY_TEST_DEDUP = 'repository.test:';

/** An ability no producer targets, so holding it shows an actor this file's rows and no others. */
const REPOSITORY_TEST_ABILITY = 'corex_notification_repository_test';

beforeEach(function () {
    $this->migrator = new Migrator();
    $this->migrator->create((new NotificationTable())->schema());
    $this->migrator->create((new NotificationUserStateTable())->schema());
    $this->repo = new WpNotificationRepository($this->migrator);

    // Independent of each other, and of a run that died before its afterEach.
    NotificationRows::forgetPrefixed(REPOSITORY_TEST_DEDUP);

    // Ids no account on a real install has, so nothing there is addressed to either.
    $this->actor = 990_000_007;
    $this->other = 990_000_008;

    $this->allow = static fn (string $ability): bool => $ability === REPOSITORY_TEST_ABILITY;
    $this->deny  = static fn (string $ability): bool => false;
});

afterEach(function () {
    NotificationRows::forgetPrefixed(REPOSITORY_TEST_DEDUP);
});

function makeNotification(NotificationRecipient $recipient, string $key = 'contact'): Notification
{
    return Notification::create(
        type: 'submission.new',
        category: NotificationCategory::SUBMISSIONS,
        severity: NotificationSeverity::ACTION,
        sourceModule: 'forms',
        titleKey: 'notifications.submission.new.title',
        messageKey: 'notifications.submission.new.body',
        rendered: ['title' => 'New submission', 'body' => 'Contact form'],
        dedupKey: REPOSITORY_TEST_DEDUP . $key,
        recipient: $recipient,
        occurredAt: new DateTimeImmutable('now'),
    );
}

it('inserts a new notification and finds it back', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor)));

    expect($stored->id)->toBeGreaterThan(0);
    $found = $this->repo->find($stored->id);
    expect($found)->not->toBeNull()
        ->and($found->dedupKey)->toBe(REPOSITORY_TEST_DEDUP . 'contact')
        ->and($found->occurrences)->toBe(1);
});

it('merges a repeat by dedup key into one record with an incremented count', function () {
    $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor)));
    $second = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor)));

    expect($second->occurrences)->toBe(2);
    // Only one row exists for that dedup key.
    expect(NotificationRows::countPrefixed(REPOSITORY_TEST_DEDUP . 'contact'))->toBe(1);
});

it('returns a user-targeted notification to that user only, never to others', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor)));

    $mine = $this->repo->queryForActor(NotificationQuery::fromRequest([]), $this->actor, $this->allow);
    $theirs = $this->repo->queryForActor(NotificationQuery::fromRequest([]), $this->other, $this->allow);

    expect($mine['total'])->toBe(1)
        ->and($mine['items'][0]['id'])->toBe($stored->id)
        ->and($theirs['total'])->toBe(0);   // FR-003: not visible, not counted
});

it('filters by the per-user status instead of ignoring the filter', function () {
    // NotificationQuery::$status was accepted at the REST boundary, validated, and then never used
    // by any read — `?status=read` returned everything with a 200. These assertions fail if the
    // filter is dropped again.
    $unread = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'status.unread:1'));
    $read   = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'status.read:1'));
    $this->repo->markRead((int) $read->id, $this->actor);

    $readOnly = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'read']),
        $this->actor,
        $this->allow,
    );
    $unreadOnly = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'unread']),
        $this->actor,
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
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'status.resolved:1'));
    $this->repo->resolveByDedupKey(REPOSITORY_TEST_DEDUP . 'status.resolved:1', 'condition cleared', new DateTimeImmutable('now'));

    $resolved = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'resolved']),
        $this->actor,
        $this->allow,
    );

    expect($resolved['total'])->toBe(1)
        ->and($resolved['items'][0]['id'])->toBe($stored->id);
});

it('narrows "assigned to me" to notifications that name the actor, not everything they can see', function () {
    // Both are visible to the actor (who holds the ability here); only one is theirs personally.
    $mine = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'assigned.mine:1'));
    $this->repo->upsertByDedupKey(
        makeNotification(NotificationRecipient::forAbility(REPOSITORY_TEST_ABILITY), 'assigned.broadcast:1')
    );

    $all      = $this->repo->queryForActor(NotificationQuery::fromRequest([]), $this->actor, $this->allow);
    $assigned = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['assigned_to_me' => true]),
        $this->actor,
        $this->allow,
    );

    expect($all['total'])->toBe(2)
        ->and($assigned['total'])->toBe(1)
        ->and($assigned['items'][0]['id'])->toBe($mine->id);
});

it('does not leak an ability-targeted notification to a user lacking the ability', function () {
    $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forAbility(REPOSITORY_TEST_ABILITY), 'ability.only:1'));

    $holder = $this->repo->queryForActor(NotificationQuery::fromRequest([]), $this->actor, $this->allow);
    $lacker = $this->repo->queryForActor(NotificationQuery::fromRequest([]), $this->actor, $this->deny);

    expect($holder['total'])->toBe(1)
        ->and($lacker['total'])->toBe(0);
});

it('counts only unread, visible notifications for the actor', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor)));

    expect($this->repo->unreadCountForActor($this->actor, $this->allow))->toBe(1)
        ->and($this->repo->unreadCountForActor($this->other, $this->allow))->toBe(0);

    $this->repo->markRead($stored->id, $this->actor);
    expect($this->repo->unreadCountForActor($this->actor, $this->allow))->toBe(0);
});

it('agrees with the unread view: a snoozed notification is neither counted nor listed', function () {
    // The bell badge and the "Requires attention" list must not disagree. The count used to exclude
    // only read/dismissed while the list derives a full status, so a snoozed item was counted but
    // not listed — a badge promising items the screen would not show, and an optional widget that
    // could register on the count and then render "all caught up".
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'snoozed.count:1'));
    $this->repo->snooze((int) $stored->id, $this->actor, new DateTimeImmutable('+1 day'));

    $listed = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'unread']),
        $this->actor,
        $this->allow,
    );

    expect($this->repo->unreadCountForActor($this->actor, $this->allow))->toBe(0)
        ->and($listed['total'])->toBe(0);
});

it('marks all read without trampling a snooze the user deliberately set', function () {
    // "Mark all as read" is offered from surfaces that show only unread items. Marking a snoozed
    // item read would silently cancel its resurfacing — the user asked to be reminded later, not to
    // have it filed away — so the sweep must touch only what is actually unread.
    $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'sweep.unread:1'));
    $snoozed = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'sweep.snoozed:1'));
    $this->repo->snooze((int) $snoozed->id, $this->actor, new DateTimeImmutable('+1 day'));

    $marked = $this->repo->markAllVisibleRead($this->actor, $this->allow);

    $stillSnoozed = $this->repo->queryForActor(
        NotificationQuery::fromRequest(['status' => 'snoozed']),
        $this->actor,
        $this->allow,
    );

    expect($marked)->toBe(1)                                            // only the unread one
        ->and($this->repo->unreadCountForActor($this->actor, $this->allow))->toBe(0) // badge still clears
        ->and($stillSnoozed['total'])->toBe(1);                          // snooze survives
});

it('keeps per-user read state private to each user', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUsers([$this->actor, $this->other])));

    $this->repo->markRead($stored->id, $this->actor);

    expect($this->repo->unreadCountForActor($this->actor, $this->allow))->toBe(0)  // 7 read it
        ->and($this->repo->unreadCountForActor($this->other, $this->allow))->toBe(1); // 8 still unread
});

it('refuses to mark read a notification the actor cannot see', function () {
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor)));

    expect($this->repo->markRead($stored->id, $this->other))->toBeFalse(); // not their notification
    expect($this->repo->unreadCountForActor($this->actor, $this->allow))->toBe(1); // unchanged
});

it('resolves and reopens a condition by dedup key, independent of user dismissal', function () {
    // Addressed to the actor by id, so the dismissal below is one the repository accepts: sight
    // through an ability is checked against the signed-in user, and nobody is signed in here.
    $condition = REPOSITORY_TEST_DEDUP . 'condition:1';
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'condition:1'));
    expect($this->repo->dismiss($stored->id, $this->actor))->toBeTrue(); // one user hides it

    $resolvedCount = $this->repo->resolveByDedupKey($condition, 'HTTPS is now configured.', new DateTimeImmutable('now'));
    expect($resolvedCount)->toBe(1)
        ->and($this->repo->find($stored->id)->isResolved())->toBeTrue();

    // The condition recurs: a fresh occurrence reopens it.
    $reopened = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'condition:1'));
    expect($reopened->isResolved())->toBeFalse()
        ->and($reopened->occurrences)->toBe(2);
});

it('prunes resolved notifications older than the cutoff, in bounded batches', function () {
    global $wpdb;
    $stored = $this->repo->upsertByDedupKey(makeNotification(NotificationRecipient::forUser($this->actor), 'old.thing:1'));
    // Backdate + resolve it well before the cutoff. The prune has no scope to give it — it removes
    // whatever on the install is resolved and older than the cutoff — so the cutoff is a date
    // before WordPress existed, which only a row backdated on purpose can be older than.
    $wpdb->update(
        $this->migrator->fullName(NotificationTable::NAME),
        ['resolved_at' => '1999-01-01 00:00:00', 'latest_occurred_at' => '1999-01-01 00:00:00'],
        ['id' => $stored->id],
    );

    $removed = $this->repo->pruneOlderThan(new DateTimeImmutable('2000-01-01'), 500);
    expect($removed)->toBe(1)
        ->and($this->repo->find($stored->id))->toBeNull();
});
