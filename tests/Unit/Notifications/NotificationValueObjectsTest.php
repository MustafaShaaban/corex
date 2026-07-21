<?php

/**
 * Unit tests for the Notification Center value objects (spec 072 US1/US3: FR-004, FR-006, FR-007, FR-011).
 *
 * The security-critical piece is NotificationRecipient::canBeSeenBy — every read and count re-checks
 * it, so a wrong answer leaks a notification. The Notification VO must also reject secret-bearing
 * metadata, mirroring ActivityEvent.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Corex\Notifications\Notification;
use Corex\Notifications\NotificationAction;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationSeverity;
use Corex\Notifications\NotificationStatus;

function aRecipient(): NotificationRecipient
{
    return NotificationRecipient::forUser(7);
}

function aNotification(NotificationRecipient $recipient = null, array $metadata = []): Notification
{
    return Notification::create(
        type: 'submission.new',
        category: NotificationCategory::SUBMISSIONS,
        severity: NotificationSeverity::ACTION,
        sourceModule: 'forms',
        titleKey: 'notifications.submission.new.title',
        messageKey: 'notifications.submission.new.body',
        rendered: ['title' => 'New submission', 'body' => 'Contact form'],
        dedupKey: 'submission.new:contact',
        recipient: $recipient ?? aRecipient(),
        occurredAt: new DateTimeImmutable('2026-07-21T10:00:00+00:00'),
        metadata: $metadata,
    );
}

// ---- closed vocabularies ----

it('accepts only in-vocabulary severity, category, and status', function () {
    expect(NotificationSeverity::isValid('critical'))->toBeTrue()
        ->and(NotificationSeverity::isValid('whatever'))->toBeFalse()
        ->and(NotificationCategory::isValid('email'))->toBeTrue()
        ->and(NotificationCategory::isValid('nope'))->toBeFalse()
        ->and(NotificationStatus::isValid('snoozed'))->toBeTrue()
        ->and(NotificationStatus::isValid('nope'))->toBeFalse();
});

it('ranks severity so a floor comparison is possible', function () {
    // critical is the most severe; success the least.
    expect(NotificationSeverity::rank('critical'))->toBeGreaterThan(NotificationSeverity::rank('warning'))
        ->and(NotificationSeverity::rank('warning'))->toBeGreaterThan(NotificationSeverity::rank('information'));
});

it('rejects an out-of-vocabulary severity at construction', function () {
    aNotificationWithSeverity('not-a-severity');
})->throws(InvalidArgumentException::class);

function aNotificationWithSeverity(string $severity): Notification
{
    return Notification::create(
        type: 'x.y',
        category: NotificationCategory::SYSTEM,
        severity: $severity,
        sourceModule: 'system',
        titleKey: 't',
        messageKey: 'm',
        rendered: ['title' => 'T', 'body' => 'B'],
        dedupKey: 'x.y:1',
        recipient: aRecipient(),
        occurredAt: new DateTimeImmutable('now'),
    );
}

// ---- recipient visibility (the security predicate) ----

it('shows a user-targeted notification only to that user', function () {
    $r = NotificationRecipient::forUser(7);
    $userCan = static fn (string $ability): bool => true;

    expect($r->canBeSeenBy(7, $userCan))->toBeTrue()
        ->and($r->canBeSeenBy(8, $userCan))->toBeFalse();
});

it('shows an ability-targeted notification only to holders of that ability', function () {
    $r = NotificationRecipient::forAbility('corex_manage_email');
    $emailManager = static fn (string $ability): bool => $ability === 'corex_manage_email';
    $other = static fn (string $ability): bool => false;

    expect($r->canBeSeenBy(7, $emailManager))->toBeTrue()
        ->and($r->canBeSeenBy(7, $other))->toBeFalse();
});

it('shows a multi-user notification to each targeted user and no one else', function () {
    $r = NotificationRecipient::forUsers([7, 9]);
    $userCan = static fn (string $ability): bool => true;

    expect($r->canBeSeenBy(7, $userCan))->toBeTrue()
        ->and($r->canBeSeenBy(9, $userCan))->toBeTrue()
        ->and($r->canBeSeenBy(8, $userCan))->toBeFalse();
});

it('gates an assigned notification on the manage ability or being the assignee', function () {
    // assigned to user 7 on a submission; permitted managers hold corex_manage_submissions.
    $r = NotificationRecipient::forAssigned('submission', '42', assigneeId: 7, managerAbility: 'corex_manage_submissions');
    $manager = static fn (string $ability): bool => $ability === 'corex_manage_submissions';
    $stranger = static fn (string $ability): bool => false;

    expect($r->canBeSeenBy(7, $stranger))->toBeTrue()   // the assignee
        ->and($r->canBeSeenBy(99, $manager))->toBeTrue() // a permitted manager
        ->and($r->canBeSeenBy(99, $stranger))->toBeFalse();
});

// ---- the Notification aggregate ----

it('is secret-free by construction — rejects a token-bearing metadata key', function () {
    aNotification(null, ['api_token' => 'abc123']);
})->throws(InvalidArgumentException::class);

it('increments occurrences and advances the latest timestamp on a repeat', function () {
    $first = aNotification();
    $later = new DateTimeImmutable('2026-07-21T11:00:00+00:00');
    $repeated = $first->withOccurrence($later);

    expect($repeated->occurrences)->toBe(2)
        ->and($repeated->latestOccurredAt->format('c'))->toBe($later->format('c'))
        ->and($repeated->firstOccurredAt->format('c'))->toBe($first->firstOccurredAt->format('c'));
});

it('marks itself resolved with a reason distinct from any user dismissal', function () {
    $resolved = aNotification()->resolved('The blocker cleared.', new DateTimeImmutable('2026-07-21T12:00:00+00:00'));

    expect($resolved->resolvedAt)->not->toBeNull()
        ->and($resolved->resolutionReason)->toBe('The blocker cleared.');
});

it('round-trips through its array projection', function () {
    $n = aNotification()->withId(5);
    $wire = $n->toArray();
    $restored = Notification::fromArray($wire);

    expect($restored->id)->toBe(5)
        ->and($restored->type)->toBe('submission.new')
        ->and($restored->dedupKey)->toBe('submission.new:contact')
        ->and($restored->recipient->canBeSeenBy(7, static fn (string $a): bool => true))->toBeTrue();
});

it('offers a direct action as navigation only, gated on an optional ability', function () {
    $action = NotificationAction::to('notifications.action.review', 'admin.php?page=corex-submissions', 'corex_manage_submissions');

    expect($action->url)->toBe('admin.php?page=corex-submissions')
        ->and($action->ability)->toBe('corex_manage_submissions');
});
