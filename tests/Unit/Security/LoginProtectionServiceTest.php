<?php

/**
 * Unit tests for Spec 068 login protection policy, lockouts, retention, and trusted proxy handling.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\ClientIpResolver;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginAttemptStore;
use Corex\Config\Security\LoginProtection\LoginProtectionContext;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionService;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;

function loginPolicy(array $overrides = []): LoginProtectionSettings
{
    return new LoginProtectionSettings(
        enabled: $overrides['enabled'] ?? true,
        customSlug: $overrides['customSlug'] ?? 'team-login',
        blockDefaultEndpoints: $overrides['blockDefaultEndpoints'] ?? true,
        threshold: $overrides['threshold'] ?? 3,
        windowSeconds: $overrides['windowSeconds'] ?? 300,
        lockoutSeconds: $overrides['lockoutSeconds'] ?? 900,
        trustedProxyMode: $overrides['trustedProxyMode'] ?? false,
        trustedProxyRanges: $overrides['trustedProxyRanges'] ?? [],
        retainDays: $overrides['retainDays'] ?? 30,
        successfulLoginLogging: $overrides['successfulLoginLogging'] ?? true,
    );
}

function loginContext(string $identity = 'owner@example.com', string $ip = '203.0.113.9'): LoginProtectionContext
{
    return new LoginProtectionContext(
        identity: $identity,
        clientIp: $ip,
        userAgent: 'CoreX Test Browser',
    );
}

it('locks a repeated failed login by identity and network without storing raw credentials or IPs', function () {
    $store = new CorexTestLoginAttemptStore();
    $service = new LoginProtectionService(new LoginProtectionPolicy(loginPolicy()), $store);
    $now = new DateTimeImmutable('2026-07-07T12:00:00+00:00');

    expect($service->recordFailure(loginContext(), $now)->locked)->toBeFalse()
        ->and($service->recordFailure(loginContext(), $now->modify('+1 minute'))->locked)->toBeFalse();

    $locked = $service->recordFailure(loginContext(), $now->modify('+2 minutes'));

    expect($locked->locked)->toBeTrue()
        ->and($locked->reasonCode)->toBe('threshold_exceeded')
        ->and($locked->lockedUntil?->format(DATE_ATOM))->toBe('2026-07-07T12:17:00+00:00')
        ->and($service->assess(loginContext(), $now->modify('+3 minutes'))->locked)->toBeTrue()
        ->and($store->records)->toHaveCount(3)
        ->and($store->records[0]->identityHash)->not->toContain('owner@example.com')
        ->and($store->records[0]->networkHash)->not->toContain('203.0.113.9')
        ->and($store->records[0]->retentionUntil->format('Y-m-d'))->toBe('2026-08-06');
});

it('ignores failures outside the configured window and releases after the lockout expires', function () {
    $store = new CorexTestLoginAttemptStore();
    $service = new LoginProtectionService(new LoginProtectionPolicy(loginPolicy()), $store);
    $now = new DateTimeImmutable('2026-07-07T12:00:00+00:00');

    $service->recordFailure(loginContext(), $now->modify('-10 minutes'));
    $service->recordFailure(loginContext(), $now->modify('-9 minutes'));
    $allowed = $service->recordFailure(loginContext(), $now);

    expect($allowed->locked)->toBeFalse();

    $service->recordFailure(loginContext(), $now->modify('+1 minute'));
    $locked = $service->recordFailure(loginContext(), $now->modify('+2 minutes'));

    expect($locked->locked)->toBeTrue()
        ->and($service->assess(loginContext(), $now->modify('+20 minutes'))->locked)->toBeFalse();
});

it('logs successful sign-ins only when the policy enables success logging', function () {
    $store = new CorexTestLoginAttemptStore();
    $enabled = new LoginProtectionService(new LoginProtectionPolicy(loginPolicy()), $store);
    $disabled = new LoginProtectionService(
        new LoginProtectionPolicy(loginPolicy(['successfulLoginLogging' => false])),
        $store,
    );

    $enabled->recordSuccess(loginContext(), userId: 15, now: new DateTimeImmutable('2026-07-07T12:00:00+00:00'));
    $disabled->recordSuccess(loginContext('editor@example.com'), userId: 16, now: new DateTimeImmutable('2026-07-07T12:01:00+00:00'));

    expect($store->records)->toHaveCount(1)
        ->and($store->records[0]->outcome)->toBe('success')
        ->and($store->records[0]->userId)->toBe(15);
});

it('resolves trusted proxy client addresses without accepting spoofed forwarded headers', function () {
    $resolver = new ClientIpResolver(loginPolicy([
        'trustedProxyMode' => true,
        'trustedProxyRanges' => ['10.0.0.0/8', '2001:db8::/32'],
    ]));

    expect($resolver->resolve([
        'REMOTE_ADDR' => '10.1.2.3',
        'HTTP_X_FORWARDED_FOR' => '203.0.113.9, 10.1.2.3',
    ]))->toBe('203.0.113.9')
        ->and($resolver->resolve([
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => '203.0.113.99',
        ]))->toBe('198.51.100.7')
        ->and($resolver->resolve([
            'REMOTE_ADDR' => '2001:db8::10',
            'HTTP_X_FORWARDED_FOR' => '2001:db9::1, 2001:db8::10',
        ]))->toBe('2001:db9::1');
});

it('does not record or lock out when login protection is disabled', function () {
    $store = new CorexTestLoginAttemptStore();
    $service = new LoginProtectionService(new LoginProtectionPolicy(loginPolicy(['enabled' => false])), $store);

    $decision = $service->recordFailure(loginContext(), new DateTimeImmutable('2026-07-07T12:00:00+00:00'));

    expect($decision->locked)->toBeFalse()
        ->and($decision->reasonCode)->toBe('disabled')
        ->and($store->records)->toBe([]);
});

final class CorexTestLoginAttemptStore implements LoginAttemptStore
{
    /** @var list<LoginAttemptRecord> */
    public array $records = [];

    public function record(LoginAttemptRecord $record): void
    {
        $this->records[] = $record;
    }

    public function failures(string $identityHash, string $networkHash, DateTimeImmutable $since): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (LoginAttemptRecord $record): bool =>
                $record->outcome === 'failed'
                && $record->identityHash === $identityHash
                && $record->networkHash === $networkHash
                && $record->occurredAt >= $since,
        ));
    }

    public function latestLockout(string $identityHash, string $networkHash, DateTimeImmutable $now): ?LoginAttemptRecord
    {
        foreach (array_reverse($this->records) as $record) {
            if (
                $record->outcome === 'locked'
                && $record->identityHash === $identityHash
                && $record->networkHash === $networkHash
                && $record->lockedUntil !== null
                && $record->lockedUntil > $now
            ) {
                return $record;
            }
        }

        return null;
    }
}
