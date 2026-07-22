<?php

/**
 * Unit tests for deriving a notification's per-user status (spec 072 FR-010).
 *
 * `NotificationStatus` documents itself as "derived from the shared record plus the user's state
 * row", but nothing derived it — so `NotificationQuery::$status` was accepted at the REST boundary,
 * validated, and then silently ignored by every read. These tests pin the precedence the derivation
 * uses, because the interesting cases are the collisions: a resolved condition the user never read,
 * a dismissed item that is also snoozed, an expired one that was read.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Corex\Notifications\NotificationStatus;

$at = static fn (string $when): DateTimeImmutable => new DateTimeImmutable($when);
$now = new DateTimeImmutable('2026-07-22 12:00:00');

it('reports unread when the user has done nothing and the condition is live', function () use ($now) {
    expect(NotificationStatus::derive(null, null, [], $now))->toBe(NotificationStatus::UNREAD);
});

it('reports read once the user has read it', function () use ($now) {
    expect(NotificationStatus::derive(null, null, ['read_at' => '2026-07-22 11:00:00'], $now))
        ->toBe(NotificationStatus::READ);
});

it('reports snoozed only while the snooze is still in the future', function () use ($at, $now) {
    expect(NotificationStatus::derive(null, null, ['snoozed_until' => '2026-07-22 18:00:00'], $now))
        ->toBe(NotificationStatus::SNOOZED)
        // An elapsed snooze is not a status — the item is simply back, and unread unless read.
        ->and(NotificationStatus::derive(null, null, ['snoozed_until' => '2026-07-22 06:00:00'], $now))
        ->toBe(NotificationStatus::UNREAD);
});

it('reports dismissed even when the user had also read or snoozed it', function () use ($now) {
    expect(NotificationStatus::derive(null, null, [
        'read_at'       => '2026-07-22 09:00:00',
        'snoozed_until' => '2026-07-22 18:00:00',
        'dismissed_at'  => '2026-07-22 10:00:00',
    ], $now))->toBe(NotificationStatus::DISMISSED);
});

it('reports resolved regardless of what the user did, because the condition ended', function () use ($now) {
    // Resolution is a property of the shared record; one user dismissing it never resolves it, and
    // resolution outranks any personal state (FR-010).
    expect(NotificationStatus::derive('2026-07-22 10:00:00', null, [
        'dismissed_at' => '2026-07-22 09:00:00',
        'read_at'      => '2026-07-22 09:00:00',
    ], $now))->toBe(NotificationStatus::RESOLVED);
});

it('reports expired once the expiry has passed, but not before', function () use ($now) {
    expect(NotificationStatus::derive(null, '2026-07-22 06:00:00', [], $now))
        ->toBe(NotificationStatus::EXPIRED)
        ->and(NotificationStatus::derive(null, '2026-07-22 18:00:00', [], $now))
        ->toBe(NotificationStatus::UNREAD);
});

it('prefers resolved over expired when both are true', function () use ($now) {
    expect(NotificationStatus::derive('2026-07-22 08:00:00', '2026-07-22 06:00:00', [], $now))
        ->toBe(NotificationStatus::RESOLVED);
});

it('only ever returns a status from its own vocabulary', function () use ($now) {
    $derived = NotificationStatus::derive('2026-07-22 08:00:00', '2026-07-22 06:00:00', [
        'read_at'       => '2026-07-22 07:00:00',
        'dismissed_at'  => '2026-07-22 07:30:00',
        'snoozed_until' => '2026-07-22 20:00:00',
    ], $now);

    expect(NotificationStatus::isValid($derived))->toBeTrue();
});
