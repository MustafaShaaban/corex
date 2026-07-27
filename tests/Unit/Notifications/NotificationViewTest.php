<?php

/**
 * Read is not resolved (spec 074, FR-4.1).
 *
 * The defect: "Requires attention" filtered on the actor's *unread* state, so opening a production
 * readiness blocker took it off the attention list while the blocker was still true. These tests
 * pin the rule that replaces it — an item leaves Action needed when the condition ends, when the
 * actor dismisses or snoozes it, or when it expires. Never merely because somebody looked at it.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Corex\Notifications\NotificationSeverity;
use Corex\Notifications\NotificationStatus;
use Corex\Notifications\NotificationView;

it('keeps a read but unresolved condition in action needed', function () {
    foreach ([NotificationStatus::UNREAD, NotificationStatus::READ] as $status) {
        expect(NotificationView::of($status, NotificationSeverity::CRITICAL, false))
            ->toBe(NotificationView::ACTION_NEEDED)
            ->and(NotificationView::needsAction($status, NotificationSeverity::ERROR, false))
            ->toBeTrue();
    }
});

it('treats every demanding severity as asking something', function () {
    foreach ([
        NotificationSeverity::CRITICAL,
        NotificationSeverity::ERROR,
        NotificationSeverity::WARNING,
        NotificationSeverity::ACTION,
    ] as $severity) {
        expect(NotificationView::of(NotificationStatus::READ, $severity, false))
            ->toBe(NotificationView::ACTION_NEEDED);
    }
});

it('files a report that asks nothing under updates', function () {
    foreach ([NotificationSeverity::INFORMATION, NotificationSeverity::SUCCESS] as $severity) {
        expect(NotificationView::of(NotificationStatus::UNREAD, $severity, false))
            ->toBe(NotificationView::UPDATES);
    }
});

it('puts an informational notification with a real action back in action needed', function () {
    // "Your CSV export is ready" is only worth a tab if you can actually download it from there.
    expect(NotificationView::of(NotificationStatus::UNREAD, NotificationSeverity::SUCCESS, true))
        ->toBe(NotificationView::ACTION_NEEDED);
});

it('files resolved, dismissed, and expired items in history', function () {
    foreach ([
        NotificationStatus::RESOLVED,
        NotificationStatus::DISMISSED,
        NotificationStatus::EXPIRED,
    ] as $status) {
        expect(NotificationView::of($status, NotificationSeverity::CRITICAL, true))
            ->toBe(NotificationView::HISTORY)
            ->and(NotificationView::needsAction($status, NotificationSeverity::CRITICAL, true))
            ->toBeFalse();
    }
});

it('moves a snoozed item to updates rather than to history', function () {
    // Snoozing is "not now", not "done" — and when the snooze elapses the derived status returns
    // to read/unread, which puts the item back in action needed with no further bookkeeping.
    expect(NotificationView::of(NotificationStatus::SNOOZED, NotificationSeverity::CRITICAL, true))
        ->toBe(NotificationView::UPDATES);
});

it('names exactly the three item views', function () {
    expect(NotificationView::all())->toBe(['action_needed', 'updates', 'history'])
        ->and(NotificationView::isValid('action_needed'))->toBeTrue()
        ->and(NotificationView::isValid('preferences'))->toBeFalse();
});

it('reopens a resolved condition when a new occurrence arrives', function () {
    // Notification::withOccurrence clears resolvedAt, so the derived status leaves `resolved` and
    // the item returns to action needed — the recurrence is the signal that it is live again.
    $reopened = NotificationStatus::derive(
        resolvedAt: null,
        expiresAt: null,
        userState: ['read_at' => '2026-07-01 00:00:00'],
        now: new DateTimeImmutable('2026-07-27 00:00:00'),
    );

    expect($reopened)->toBe(NotificationStatus::READ)
        ->and(NotificationView::of($reopened, NotificationSeverity::ERROR, false))
        ->toBe(NotificationView::ACTION_NEEDED);
});
