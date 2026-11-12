<?php

/**
 * Unit tests for the login-lockout notification producer (spec 072 US4: FR-013; spec 124 US-SEC-10).
 *
 * A brute-force lockout becomes a security notification for the operations/security managers.
 * Repeated lockouts of the same identity merge into one growing notification (occurrence count),
 * not a flood — so a sustained attack reads as one escalating signal.
 *
 * Since spec 124 the notice names the account a username belongs to, or says it was an unknown
 * username and gives a short fingerprint, and it never carries the typed username or the address
 * (FR-016, FR-020).
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Support\AdminUrlStubs;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\LoginLockoutNotificationProducer;
use Corex\Config\Security\LoginProtection\LoginAccounts;
use Corex\Events\ListenerProvider;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationService;
use Corex\Tests\Support\RecordingNotificationService;
use Corex\Security\LoginLockoutEvent;

beforeEach(function () {
    Functions\stubTranslationFunctions();
    // Producers name a destination now, and building it calls WordPress (spec 087, FR-014).
    AdminUrlStubs::install();
});

/** A NotificationService that records every published notification. */
function lockoutRecordingService(): NotificationService
{
    return new RecordingNotificationService();
}

/** The accounts the producer names: account 42 is "Sara Example". */
function lockoutAccounts(): LoginAccounts
{
    return new class () implements LoginAccounts {
        public function idFor(string $typed): ?int
        {
            return null;
        }

        public function nameOf(int $userId): string
        {
            return $userId === 42 ? 'Sara Example' : 'User ' . $userId;
        }
    };
}

function fireLockout(NotificationService $service, LoginLockoutEvent $event): NotificationService
{
    $listeners = new ListenerProvider();
    (new LoginLockoutNotificationProducer($service, $listeners, lockoutAccounts()))->register();
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return $service;
}

function unknownLockout(string $identity = 'a1b2c3d4e5f6', string $network = '0f1e2d3c4b5a'): LoginLockoutEvent
{
    return new LoginLockoutEvent(null, $identity, $network, new DateTimeImmutable('2026-07-21T10:00:00Z'));
}

/** Everything the notification stores, as one string to search. */
function storedLockoutText(Notification $note): string
{
    return (string) json_encode([
        $note->rendered,
        $note->dedupKey,
        $note->sourceType,
        $note->sourceId,
        $note->metadata,
        $note->titleKey,
        $note->messageKey,
    ]);
}

it('publishes a security-lockout notification to the operations managers', function () {
    $service = fireLockout(lockoutRecordingService(), unknownLockout());

    expect($service->published)->toHaveCount(1);
    $note = $service->published[0];
    expect($note->type)->toBe('security.lockout')
        ->and($note->category)->toBe(NotificationCategory::SECURITY)
        ->and($note->recipient->canBeSeenBy(9, fn (string $a): bool => $a === CorexAbility::MANAGE_OPERATIONS))->toBeTrue();
});

it('US-SEC-10 scenario 1: says "unknown username" and a short fingerprint for a username of no account', function () {
    $note = fireLockout(lockoutRecordingService(), unknownLockout())->published[0];

    expect($note->rendered['title'])->toContain('unknown username')
        ->and($note->rendered['body'])->toContain('a1b2c3d4e5f6')
        ->and($note->rendered['body'])->toContain('0f1e2d3c4b5a')
        ->and($note->dedupKey)->toBe('security.lockout:identity:a1b2c3d4e5f6')
        ->and($note->sourceType)->toBe('login_fingerprint')
        ->and($note->sourceId)->toBe('a1b2c3d4e5f6')
        ->and($note->metadata)->toBe([
            'locked_until'         => '2026-07-21T10:00:00+00:00',
            'user_id'              => null,
            'identity_fingerprint' => 'a1b2c3d4e5f6',
            'network_fingerprint'  => '0f1e2d3c4b5a',
        ]);
});

it('US-SEC-10 scenario 2, FR-016: names the account and shows no address', function () {
    $note = fireLockout(
        lockoutRecordingService(),
        new LoginLockoutEvent(42, 'a1b2c3d4e5f6', '0f1e2d3c4b5a', new DateTimeImmutable('2026-07-21T10:00:00Z')),
    )->published[0];

    expect($note->rendered['title'])->toContain('Sara Example')
        ->and($note->rendered['title'])->not->toContain('unknown username')
        ->and($note->rendered['body'])->toContain('0f1e2d3c4b5a')
        ->and($note->dedupKey)->toBe('security.lockout:account:42')
        ->and($note->sourceType)->toBe('login_account')
        ->and($note->sourceId)->toBe('42')
        ->and($note->metadata['user_id'])->toBe(42)
        ->and(array_keys($note->metadata))->not->toContain('client_ip');
});

it('FR-020: keeps nothing but fingerprints, an account id and the end of the lockout', function () {
    $note = fireLockout(lockoutRecordingService(), unknownLockout())->published[0];

    // A fingerprint is twelve hexadecimal characters; an address has dots or colons.
    expect(storedLockoutText($note))->not->toMatch('/\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}/')
        ->and(array_keys($note->metadata))->not->toContain('client_ip');
});

it('keys the notification by identity so repeated lockouts merge', function () {
    $one = fireLockout(lockoutRecordingService(), unknownLockout('a1b2c3d4e5f6', '111111111111'));
    $two = fireLockout(lockoutRecordingService(), unknownLockout('a1b2c3d4e5f6', '222222222222'));

    // Same identity from two networks → the same dedup key, so the store merges them into one record.
    expect($one->published[0]->dedupKey)->toBe($two->published[0]->dedupKey);
});

it('FR-020: refuses an event that carries anything but a fingerprint', function (string $identity, string $network) {
    new LoginLockoutEvent(null, $identity, $network, new DateTimeImmutable('now'));
})->with([
    'a typed username'     => ['editor@example.com', '0f1e2d3c4b5a'],
    'an address'           => ['a1b2c3d4e5f6', '203.0.113.9'],
    'a whole keyed value'  => [str_repeat('ab', 32), '0f1e2d3c4b5a'],
])->throws(InvalidArgumentException::class);

it('is keyed and available like the other producers', function () {
    $producer = new LoginLockoutNotificationProducer(lockoutRecordingService(), new ListenerProvider(), lockoutAccounts());

    expect($producer->key())->toBe('security.lockouts')
        ->and($producer->isAvailable())->toBe(class_exists(LoginLockoutEvent::class));
});
