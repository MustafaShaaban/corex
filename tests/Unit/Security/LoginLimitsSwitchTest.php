<?php

/**
 * Failed sign-ins are limited by their own switch (spec 124, FR-013, User Story 6 scenario 3).
 *
 * Limits were on whenever protection was on. Now the hiding switches alone limit nothing, and the
 * limits switch alone limits without hiding anything.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginAccounts;
use Corex\Config\Security\LoginProtection\LoginAttemptKeys;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginAttemptStore;
use Corex\Config\Security\LoginProtection\LoginHashKey;
use Corex\Config\Security\LoginProtection\LoginProtectionContext;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionService;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginSwitches;

final class LimitsSwitchAttempts implements LoginAttemptStore
{
    /** @var list<LoginAttemptRecord> */
    public array $records = [];

    public function record(LoginAttemptRecord $record): void
    {
        $this->records[] = $record;
    }

    public function failures(LoginAttemptKeys $keys, DateTimeImmutable $since): array
    {
        return array_values(array_filter($this->records, static fn (LoginAttemptRecord $r): bool => $r->outcome === LoginAttemptRecord::FAILED));
    }

    public function latestLockout(LoginAttemptKeys $keys, DateTimeImmutable $now): ?LoginAttemptRecord
    {
        foreach (array_reverse($this->records) as $record) {
            if ($record->lockedUntil !== null && $record->lockedUntil > $now) {
                return $record;
            }
        }

        return null;
    }
}

function limitsSwitchService(LoginSwitches $switches, LimitsSwitchAttempts $attempts): LoginProtectionService
{
    $settings = LoginProtectionSettings::of($switches, threshold: 3);

    return new LoginProtectionService(
        new LoginProtectionPolicy($settings, new class () implements LoginHashKey {
            public function current(): string
            {
                return 'limits-switch-test-key';
            }
        }),
        $attempts,
        new class () implements LoginAccounts {
            public function idFor(string $typed): ?int
            {
                return null;
            }

            public function nameOf(int $userId): string
            {
                return '';
            }
        },
    );
}

function limitsSwitchContext(): LoginProtectionContext
{
    return new LoginProtectionContext(identity: 'owner', clientIp: '203.0.113.5', userAgent: 'Test');
}

it('FR-013: the hiding switches alone record no failed attempt and lock no one out, and still record a successful sign-in', function (LoginSwitches $switches) {
    $attempts = new LimitsSwitchAttempts();
    $service = limitsSwitchService($switches, $attempts);
    $now = new DateTimeImmutable('2026-11-12T12:00:00+00:00');

    foreach (range(1, 5) as $_) {
        $decision = $service->recordFailure(limitsSwitchContext(), $now);
    }
    $service->recordSuccess(limitsSwitchContext(), 7, $now);

    expect($decision->reasonCode)->toBe('disabled')
        ->and($service->assess(limitsSwitchContext(), $now)->locked)->toBeFalse()
        // Recording successful sign-ins keeps its previous meaning: its own setting, with any switch on.
        ->and(array_map(static fn (LoginAttemptRecord $r): string => $r->outcome, $attempts->records))->toBe([LoginAttemptRecord::SUCCESS]);
})->with([
    'hide wp-login.php' => [new LoginSwitches(true, false, false)],
    'hide the admin' => [new LoginSwitches(false, true, false)],
    'hide both' => [new LoginSwitches(true, true, false)],
]);

it('FR-013, scenario 3: the limits switch alone locks out at the threshold', function () {
    $attempts = new LimitsSwitchAttempts();
    $service = limitsSwitchService(new LoginSwitches(false, false, true), $attempts);
    $now = new DateTimeImmutable('2026-11-12T12:00:00+00:00');

    $service->recordFailure(limitsSwitchContext(), $now);
    $service->recordFailure(limitsSwitchContext(), $now);
    $third = $service->recordFailure(limitsSwitchContext(), $now);

    expect($third->reasonCode)->toBe('threshold_exceeded')
        ->and($service->assess(limitsSwitchContext(), $now)->locked)->toBeTrue();
});

it('records no successful sign-in with every switch off, or with its own setting off', function (LoginSwitches $switches, bool $logging) {
    $attempts = new LimitsSwitchAttempts();
    $service = new LoginProtectionService(
        new LoginProtectionPolicy(LoginProtectionSettings::of($switches, successfulLoginLogging: $logging), new class () implements LoginHashKey {
            public function current(): string
            {
                return 'limits-switch-test-key';
            }
        }),
        $attempts,
        new class () implements LoginAccounts {
            public function idFor(string $typed): ?int
            {
                return null;
            }

            public function nameOf(int $userId): string
            {
                return '';
            }
        },
    );

    $service->recordSuccess(limitsSwitchContext(), 7, new DateTimeImmutable('2026-11-12T12:00:00+00:00'));

    expect($attempts->records)->toBe([]);
})->with([
    'every switch off' => [LoginSwitches::off(), true],
    'the admin hidden, recording off' => [new LoginSwitches(false, true, false), false],
]);
