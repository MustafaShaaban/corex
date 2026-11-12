<?php

/**
 * Sign-in records keep the typed username and the visitor's address unreadable (spec 124, US-SEC-10).
 *
 * The values a failed sign-in is counted by were plain SHA-256 of the username and the address: anyone
 * holding the table could hash a list of names, or the whole IPv4 range, and read them back. They are
 * now keyed with a secret of the site's own (FR-019), and a lockout names its account, or says it was
 * an unknown username (FR-016, the record half).
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Security\LoginProtection\LoginAccounts;
use Corex\Config\Security\LoginProtection\LoginAttemptKeys;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginAttemptStore;
use Corex\Config\Security\LoginProtection\LoginHashKey;
use Corex\Config\Security\LoginProtection\LoginProtectionContext;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionService;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\WpLoginHashKey;
use Corex\Security\LoginLockoutEvent;

function privacySettings(int $threshold = 3): LoginProtectionSettings
{
    return new LoginProtectionSettings(
        enabled: true,
        customSlug: 'team-login',
        blockDefaultEndpoints: true,
        threshold: $threshold,
        windowSeconds: 300,
        lockoutSeconds: 900,
        trustedProxyMode: false,
        trustedProxyRanges: [],
        retainDays: 30,
        successfulLoginLogging: true,
    );
}

function privacyKey(string $key = 'site-one-key'): LoginHashKey
{
    return new class ($key) implements LoginHashKey {
        public function __construct(private readonly string $key)
        {
        }

        public function current(): string
        {
            return $this->key;
        }
    };
}

/** @param array<string,int> $accounts typed username => account id */
function privacyAccounts(array $accounts = []): LoginAccounts
{
    return new class ($accounts) implements LoginAccounts {
        /** @var list<string> */
        public array $asked = [];

        /** @param array<string,int> $accounts */
        public function __construct(private readonly array $accounts)
        {
        }

        public function idFor(string $typed): ?int
        {
            $this->asked[] = $typed;

            return $this->accounts[$typed] ?? null;
        }

        public function nameOf(int $userId): string
        {
            return 'Account ' . $userId;
        }
    };
}

function privacyContext(string $identity = 'Sara.Example', string $ip = '198.51.100.77'): LoginProtectionContext
{
    return new LoginProtectionContext(identity: $identity, clientIp: $ip, userAgent: 'CoreX Test Browser');
}

/** An attempt store that answers from the pairs it is given, as the table does. */
final class PrivacyAttemptStore implements LoginAttemptStore
{
    /** @var list<LoginAttemptRecord> */
    public array $records = [];

    public function record(LoginAttemptRecord $record): void
    {
        $this->records[] = $record;
    }

    public function failures(LoginAttemptKeys $keys, DateTimeImmutable $since): array
    {
        return array_values(array_filter(
            $this->records,
            static fn (LoginAttemptRecord $record): bool => $record->outcome === LoginAttemptRecord::FAILED
                && $keys->matches($record->identityHash, $record->networkHash)
                && $record->occurredAt >= $since,
        ));
    }

    public function latestLockout(LoginAttemptKeys $keys, DateTimeImmutable $now): ?LoginAttemptRecord
    {
        foreach (array_reverse($this->records) as $record) {
            if (
                $record->outcome === LoginAttemptRecord::LOCKED
                && $keys->matches($record->identityHash, $record->networkHash)
                && $record->lockedUntil !== null
                && $record->lockedUntil > $now
            ) {
                return $record;
            }
        }

        return null;
    }
}

/** A row stored by the release before this one: plain SHA-256, no key. */
function legacyRecord(string $outcome, DateTimeImmutable $at, ?DateTimeImmutable $lockedUntil = null): LoginAttemptRecord
{
    return new LoginAttemptRecord(
        identityHash: hash('sha256', 'sara.example'),
        networkHash: hash('sha256', '198.51.100.77'),
        outcome: $outcome,
        reasonCode: $outcome === LoginAttemptRecord::LOCKED ? 'threshold_exceeded' : 'invalid_credentials',
        userId: null,
        occurredAt: $at,
        retentionUntil: $at->modify('+30 days'),
        lockedUntil: $lockedUntil,
    );
}

it('FR-019: keys both stored values with the site key, so they are not the plain hash of what was typed', function () {
    $policy = new LoginProtectionPolicy(privacySettings(), privacyKey());

    expect($policy->identityHash(privacyContext()))
        ->toBe(hash_hmac('sha256', 'sara.example', 'site-one-key'))
        ->not->toBe(hash('sha256', 'sara.example'))
        ->and($policy->networkHash(privacyContext()))
        ->toBe(hash_hmac('sha256', '198.51.100.77', 'site-one-key'))
        ->not->toBe(hash('sha256', '198.51.100.77'));
});

it('FR-019: reads the username as the release before did, trimmed and in lower case', function () {
    $policy = new LoginProtectionPolicy(privacySettings(), privacyKey());

    expect($policy->identityHash(privacyContext('  SARA.example ')))->toBe($policy->identityHash(privacyContext('sara.example')))
        ->and($policy->keys(privacyContext('  SARA.example '))->legacyIdentityHash)->toBe(hash('sha256', 'sara.example'));
});

it('FR-019, US-SEC-10 scenario 4: the same username and address make different values on two sites', function () {
    $one = new LoginProtectionPolicy(privacySettings(), privacyKey('site-one-key'));
    $two = new LoginProtectionPolicy(privacySettings(), privacyKey('site-two-key'));

    expect($one->identityHash(privacyContext()))->not->toBe($two->identityHash(privacyContext()))
        ->and($one->networkHash(privacyContext()))->not->toBe($two->networkHash(privacyContext()));
});

it('FR-019: makes the site key from the auth salt and the site id, and never hands out the salt', function () {
    Functions\when('wp_salt')->alias(static fn (string $scheme): string => 'salt-for-' . $scheme);
    $blog = 1;
    Functions\when('get_current_blog_id')->alias(static function () use (&$blog): int {
        return $blog;
    });
    $key = new WpLoginHashKey();

    $siteOne = $key->current();
    $blog = 7;
    $siteSeven = $key->current();

    expect($siteOne)->toBe(hash_hmac('sha256', 'corex-login-attempts:1', 'salt-for-auth'))
        ->and($siteSeven)->toBe(hash_hmac('sha256', 'corex-login-attempts:7', 'salt-for-auth'))
        ->and($siteOne)->not->toBe($siteSeven)
        ->and($siteOne)->not->toContain('salt-for-auth');
});

it('FR-019: records every new attempt under the keyed values only', function () {
    $store = new PrivacyAttemptStore();
    $policy = new LoginProtectionPolicy(privacySettings(), privacyKey());
    $service = new LoginProtectionService($policy, $store, privacyAccounts());
    $now = new DateTimeImmutable('2026-11-12T10:00:00+00:00');

    $service->recordFailure(privacyContext(), $now);
    $service->recordSuccess(privacyContext(), 4, $now->modify('+1 minute'));

    expect(array_map(static fn (LoginAttemptRecord $r): string => $r->identityHash, $store->records))
        ->toBe([$policy->identityHash(privacyContext()), $policy->identityHash(privacyContext())])
        ->and(array_map(static fn (LoginAttemptRecord $r): string => $r->networkHash, $store->records))
        ->toBe([$policy->networkHash(privacyContext()), $policy->networkHash(privacyContext())]);
});

it('FR-019: counts failures stored before the update with the new ones, until they leave the window', function () {
    $store = new PrivacyAttemptStore();
    $service = new LoginProtectionService(new LoginProtectionPolicy(privacySettings(3), privacyKey()), $store, privacyAccounts());
    $now = new DateTimeImmutable('2026-11-12T10:00:00+00:00');
    $store->records[] = legacyRecord(LoginAttemptRecord::FAILED, $now->modify('-2 minutes'));
    $store->records[] = legacyRecord(LoginAttemptRecord::FAILED, $now->modify('-1 minute'));

    // Two old failures and this one reach the threshold of three.
    expect($service->recordFailure(privacyContext(), $now)->reasonCode)->toBe('threshold_exceeded');

    // Old failures that have left the window count for nothing.
    $later = new PrivacyAttemptStore();
    $laterService = new LoginProtectionService(new LoginProtectionPolicy(privacySettings(3), privacyKey()), $later, privacyAccounts());
    $later->records[] = legacyRecord(LoginAttemptRecord::FAILED, $now->modify('-10 minutes'));
    $later->records[] = legacyRecord(LoginAttemptRecord::FAILED, $now->modify('-9 minutes'));

    expect($laterService->recordFailure(privacyContext(), $now)->locked)->toBeFalse();
});

it('FR-019, US-SEC-10 scenario 5: a lockout stored before the update still holds until its end', function () {
    $store = new PrivacyAttemptStore();
    $service = new LoginProtectionService(new LoginProtectionPolicy(privacySettings(), privacyKey()), $store, privacyAccounts());
    $now = new DateTimeImmutable('2026-11-12T10:00:00+00:00');
    $store->records[] = legacyRecord(LoginAttemptRecord::LOCKED, $now->modify('-5 minutes'), $now->modify('+10 minutes'));

    $held = $service->assess(privacyContext(), $now);
    $after = $service->assess(privacyContext(), $now->modify('+11 minutes'));

    expect($held->locked)->toBeTrue()
        ->and($held->lockedUntil?->format(DATE_ATOM))->toBe('2026-11-12T10:10:00+00:00')
        ->and($after->locked)->toBeFalse()
        // Another failure during the old lockout is refused as locked, and adds no record.
        ->and($service->recordFailure(privacyContext(), $now)->reasonCode)->toBe('active_lockout')
        ->and($store->records)->toHaveCount(1);
});

it('FR-019: never counts an old row of another username or another address', function () {
    $store = new PrivacyAttemptStore();
    $service = new LoginProtectionService(new LoginProtectionPolicy(privacySettings(), privacyKey()), $store, privacyAccounts());
    $now = new DateTimeImmutable('2026-11-12T10:00:00+00:00');
    $store->records[] = legacyRecord(LoginAttemptRecord::LOCKED, $now->modify('-1 minute'), $now->modify('+10 minutes'));

    expect($service->assess(privacyContext('someone-else'), $now)->locked)->toBeFalse()
        ->and($service->assess(privacyContext('sara.example', '198.51.100.78'), $now)->locked)->toBeFalse();
});

it('FR-016: records the account a locked-out username belongs to on the lockout row', function () {
    $store = new PrivacyAttemptStore();
    $accounts = privacyAccounts(['Sara.Example' => 42]);
    $service = new LoginProtectionService(new LoginProtectionPolicy(privacySettings(2), privacyKey()), $store, $accounts);
    $now = new DateTimeImmutable('2026-11-12T10:00:00+00:00');

    $service->recordFailure(privacyContext(), $now);
    $locked = $service->recordFailure(privacyContext(), $now->modify('+1 minute'));

    expect($locked->lockout?->userId)->toBe(42)
        ->and($store->records[1]->outcome)->toBe(LoginAttemptRecord::LOCKED)
        ->and($store->records[1]->userId)->toBe(42)
        // The failure before it is not looked up: the account is asked for once, on the way into the lockout.
        ->and($store->records[0]->userId)->toBeNull()
        ->and($accounts->asked)->toBe(['Sara.Example']);
});

it('FR-016: records no account for a username that belongs to none', function () {
    $store = new PrivacyAttemptStore();
    $service = new LoginProtectionService(new LoginProtectionPolicy(privacySettings(1), privacyKey()), $store, privacyAccounts());

    $locked = $service->recordFailure(privacyContext('nobody-here'), new DateTimeImmutable('2026-11-12T10:00:00+00:00'));

    expect($locked->locked)->toBeTrue()
        ->and($locked->lockout?->userId)->toBeNull()
        ->and($store->records[0]->userId)->toBeNull();
});

it('FR-020: announces a lockout with a short fingerprint of each stored value, keyed again, and nothing longer', function () {
    $store = new PrivacyAttemptStore();
    $policy = new LoginProtectionPolicy(privacySettings(1), privacyKey());
    $service = new LoginProtectionService($policy, $store, privacyAccounts());

    $lockout = $service->recordFailure(privacyContext(), new DateTimeImmutable('2026-11-12T10:00:00+00:00'))->lockout;

    expect($lockout)->toBeInstanceOf(LoginLockoutEvent::class)
        ->and($lockout?->identityFingerprint)->toBe($policy->fingerprint($store->records[0]->identityHash))
        ->and($lockout?->networkFingerprint)->toBe($policy->fingerprint($store->records[0]->networkHash))
        ->and($lockout?->lockedUntil->format(DATE_ATOM))->toBe('2026-11-12T10:15:00+00:00');
});

it('FR-020: makes a fingerprint that is no prefix of the value it stands for, so an old unkeyed row cannot be read back from it', function () {
    $policy = new LoginProtectionPolicy(privacySettings(), privacyKey());
    $plainAddress = hash('sha256', '198.51.100.77');

    expect($policy->fingerprint($plainAddress))
        ->toBe(substr(hash_hmac('sha256', $plainAddress, 'site-one-key'), 0, 12))
        ->toMatch('/^[0-9a-f]{12}$/')
        ->not->toBe(substr($plainAddress, 0, 12))
        // Another site's key makes another fingerprint of the same stored value.
        ->not->toBe((new LoginProtectionPolicy(privacySettings(), privacyKey('site-two-key')))->fingerprint($plainAddress));
});
