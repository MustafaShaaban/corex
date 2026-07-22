<?php

/**
 * Unit tests for the login-lockout notification producer (spec 072 US4: FR-013).
 *
 * A brute-force lockout becomes a security notification for the operations/security managers.
 * Repeated lockouts of the same identity merge into one growing notification (occurrence count),
 * not a flood — so a sustained attack reads as one escalating signal.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\LoginLockoutNotificationProducer;
use Corex\Events\ListenerProvider;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationService;
use Corex\Tests\Support\RecordingNotificationService;
use Corex\Security\LoginLockoutEvent;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

/** A NotificationService that records every published notification. */
function lockoutRecordingService(): NotificationService
{
    return new RecordingNotificationService();
}

function fireLockout(NotificationService $service, LoginLockoutEvent $event): NotificationService
{
    $listeners = new ListenerProvider();
    (new LoginLockoutNotificationProducer($service, $listeners))->register();
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return $service;
}

it('publishes a security-lockout notification to the operations managers', function () {
    $service = fireLockout(
        lockoutRecordingService(),
        new LoginLockoutEvent('editor@example.com', '203.0.113.9', new DateTimeImmutable('2026-07-21T10:00:00Z')),
    );

    expect($service->published)->toHaveCount(1);
    $note = $service->published[0];
    expect($note->type)->toBe('security.lockout')
        ->and($note->category)->toBe(NotificationCategory::SECURITY)
        ->and($note->dedupKey)->toBe('security.lockout:editor@example.com')
        ->and($note->recipient->canBeSeenBy(9, fn (string $a): bool => $a === CorexAbility::MANAGE_OPERATIONS))->toBeTrue();
});

it('keys the notification by identity so repeated lockouts merge', function () {
    $one = fireLockout(lockoutRecordingService(), new LoginLockoutEvent('admin', '198.51.100.1', new DateTimeImmutable('now')));
    $two = fireLockout(lockoutRecordingService(), new LoginLockoutEvent('admin', '198.51.100.2', new DateTimeImmutable('now')));

    // Same identity from two IPs → the same dedup key, so the store merges them into one record.
    expect($one->published[0]->dedupKey)->toBe('security.lockout:admin')
        ->and($two->published[0]->dedupKey)->toBe('security.lockout:admin');
});

it('is keyed and available like the other producers', function () {
    $producer = new LoginLockoutNotificationProducer(lockoutRecordingService(), new ListenerProvider());

    expect($producer->key())->toBe('security.lockouts')
        ->and($producer->isAvailable())->toBe(class_exists(LoginLockoutEvent::class));
});
