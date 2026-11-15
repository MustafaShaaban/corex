<?php

/**
 * Releasing a lockout from the screen (spec 124, User Story 8, FR-017), the logic without WordPress.
 *
 * A lockout is found by the number of its stored row, which the Lockouts panel sends as the row's
 * `id`: the server reads the row and ends every active lockout stored under the same identity and
 * network values, with no raw username or address needed. What was released is published once, with
 * the account or the fingerprints and never a stored hash.
 *
 * @package Corex\Tests\Unit\Security
 */

declare(strict_types=1);

use Brain\Monkey\Actions;
use Corex\Config\Security\LoginProtection\LockoutReleaseOutcome;
use Corex\Config\Security\LoginProtection\LockoutReleaser;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginHashKey;
use Corex\Config\Security\LoginProtection\LoginLockoutReleases;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionSettings;

/** Lockout rows in memory, released the way the table releases them. */
final class ReleaseTestLockouts implements LoginLockoutReleases
{
    /** @var array<int,LoginAttemptRecord> */
    public array $rows = [];

    /** @var list<array{0:string,1:string}> */
    public array $pairsReleased = [];

    public int $allReleased = 0;

    /** Rows another request ended between the read and the write. */
    public bool $endedMeanwhile = false;

    public function lockoutById(int $id): ?LoginAttemptRecord
    {
        return $this->rows[$id] ?? null;
    }

    public function releasePair(string $identityHash, string $networkHash, DateTimeImmutable $now): int
    {
        $this->pairsReleased[] = [$identityHash, $networkHash];
        if ($this->endedMeanwhile) {
            return 0;
        }

        $released = 0;
        foreach ($this->rows as $id => $row) {
            if ($row->identityHash === $identityHash && $row->networkHash === $networkHash && $row->lockedUntil > $now) {
                $this->rows[$id] = releaseTestRow($id, $row->identityHash, $row->networkHash, $now, $row->userId, LoginAttemptRecord::RELEASED_BY_OWNER);
                $released++;
            }
        }

        return $released;
    }

    public function releaseAllActive(DateTimeImmutable $now): int
    {
        $this->allReleased++;
        $released = 0;
        foreach ($this->rows as $id => $row) {
            if ($row->lockedUntil > $now) {
                $this->rows[$id] = releaseTestRow($id, $row->identityHash, $row->networkHash, $now, $row->userId, LoginAttemptRecord::RELEASED_BY_OWNER);
                $released++;
            }
        }

        return $released;
    }
}

function releaseTestRow(int $id, string $identity, string $network, DateTimeImmutable $lockedUntil, ?int $userId = null, string $reason = 'threshold_exceeded'): LoginAttemptRecord
{
    return new LoginAttemptRecord(
        identityHash: $identity,
        networkHash: $network,
        outcome: LoginAttemptRecord::LOCKED,
        reasonCode: $reason,
        userId: $userId,
        occurredAt: $lockedUntil->modify('-15 minutes'),
        retentionUntil: $lockedUntil->modify('+30 days'),
        lockedUntil: $lockedUntil,
        id: $id,
    );
}

function releaseTestPolicy(): LoginProtectionPolicy
{
    return new LoginProtectionPolicy(
        new LoginProtectionSettings(
            enabled: true,
            customSlug: 'team-login',
            blockDefaultEndpoints: false,
            threshold: 5,
            windowSeconds: 900,
            lockoutSeconds: 900,
            trustedProxyMode: false,
            trustedProxyRanges: [],
            retainDays: 30,
            successfulLoginLogging: true,
        ),
        new class () implements LoginHashKey {
            public function current(): string
            {
                return 'release-test-key';
            }
        },
    );
}

beforeEach(function () {
    $this->now = new DateTimeImmutable('2026-11-13T10:00:00+00:00');
    $this->sara = hash('sha256', 'sara-keyed');
    $this->office = hash('sha256', 'office-keyed');
    $this->lockouts = new ReleaseTestLockouts();
    $this->releaser = new LockoutReleaser($this->lockouts, releaseTestPolicy());
});

it('FR-017, User Story 8 scenario 2: ends the lockout the panel row names, and every active row of its pair', function () {
    $this->lockouts->rows[41] = releaseTestRow(41, $this->sara, $this->office, $this->now->modify('+10 minutes'), 12);
    // Two failures at once can each start a lockout of the same pair: both end.
    $this->lockouts->rows[42] = releaseTestRow(42, $this->sara, $this->office, $this->now->modify('+11 minutes'), 12);
    $other = hash('sha256', 'home-keyed');
    $this->lockouts->rows[43] = releaseTestRow(43, $this->sara, $other, $this->now->modify('+10 minutes'), 12);
    Actions\expectDone(LockoutReleaser::RELEASED)->once();

    $outcome = $this->releaser->releaseOne('41', 7, $this->now);

    expect($outcome->status)->toBe(LockoutReleaseOutcome::RELEASED)
        ->and($outcome->released)->toBe(2)
        ->and($this->lockouts->pairsReleased)->toBe([[$this->sara, $this->office]])
        ->and($this->lockouts->rows[41]->lockedUntil)->toEqual($this->now)
        ->and($this->lockouts->rows[41]->reasonCode)->toBe(LoginAttemptRecord::RELEASED_BY_OWNER)
        ->and($this->lockouts->rows[42]->lockedUntil)->toEqual($this->now)
        // The same username from another network is another lockout, and is left alone.
        ->and($this->lockouts->rows[43]->lockedUntil)->toEqual($this->now->modify('+10 minutes'));
});

it('FR-017, User Story 8 scenario 2: publishes who released whose lockout, by account and fingerprints, never a stored value', function () {
    $this->lockouts->rows[41] = releaseTestRow(41, $this->sara, $this->office, $this->now->modify('+10 minutes'), 12);
    $policy = releaseTestPolicy();
    $published = null;
    Actions\expectDone(LockoutReleaser::RELEASED)->once()->whenHappen(function (...$args) use (&$published): void {
        $published = $args;
    });

    $this->releaser->releaseOne('41', 7, $this->now);

    expect($published)->toBe([7, 1, 12, $policy->identityFingerprint($this->sara), $policy->networkFingerprint($this->office)])
        ->and((string) json_encode($published))->not->toContain($this->sara)
        ->and((string) json_encode($published))->not->toContain($this->office);
});

it('FR-016, User Story 8 scenario 3: publishes an unknown username as account 0 with its fingerprint', function () {
    $this->lockouts->rows[5] = releaseTestRow(5, $this->sara, $this->office, $this->now->modify('+10 minutes'));
    $published = null;
    Actions\expectDone(LockoutReleaser::RELEASED)->once()->whenHappen(function (...$args) use (&$published): void {
        $published = $args;
    });

    $this->releaser->releaseOne('5', 7, $this->now);

    expect($published[2])->toBe(0)
        ->and($published[3])->toBe(releaseTestPolicy()->identityFingerprint($this->sara));
});

it('FR-017, User Story 8 scenario 5: says a lockout that has already ended has ended, and changes nothing', function () {
    $this->lockouts->rows[9] = releaseTestRow(9, $this->sara, $this->office, $this->now->modify('-1 minute'));
    Actions\expectDone(LockoutReleaser::RELEASED)->never();

    $outcome = $this->releaser->releaseOne('9', 7, $this->now);

    expect($outcome->status)->toBe(LockoutReleaseOutcome::ALREADY_ENDED)
        ->and($outcome->released)->toBe(0)
        ->and($this->lockouts->pairsReleased)->toBe([]);
});

it('FR-017, User Story 8 scenario 5: a lockout ending this very second has ended', function () {
    $this->lockouts->rows[9] = releaseTestRow(9, $this->sara, $this->office, $this->now);
    Actions\expectDone(LockoutReleaser::RELEASED)->never();

    expect($this->releaser->releaseOne('9', 7, $this->now)->status)->toBe(LockoutReleaseOutcome::ALREADY_ENDED);
});

it('FR-017: a release that another request beat to the row says the lockout has ended, and is published by the one that ended it only', function () {
    $this->lockouts->rows[41] = releaseTestRow(41, $this->sara, $this->office, $this->now->modify('+10 minutes'));
    $this->lockouts->endedMeanwhile = true;
    Actions\expectDone(LockoutReleaser::RELEASED)->never();

    $outcome = $this->releaser->releaseOne('41', 7, $this->now);

    expect($outcome->status)->toBe(LockoutReleaseOutcome::ALREADY_ENDED)
        ->and($outcome->released)->toBe(0);
});

it('FR-017: an id that names no lockout is not found, and changes nothing', function (string $id) {
    // Row 3 is a failed attempt, not a lockout.
    $this->lockouts->rows[3] = new LoginAttemptRecord(
        identityHash: $this->sara,
        networkHash: $this->office,
        outcome: LoginAttemptRecord::FAILED,
        reasonCode: 'invalid_credentials',
        userId: null,
        occurredAt: $this->now,
        retentionUntil: $this->now->modify('+30 days'),
        id: 3,
    );
    Actions\expectDone(LockoutReleaser::RELEASED)->never();

    $outcome = $this->releaser->releaseOne($id, 7, $this->now);

    expect($outcome->status)->toBe(LockoutReleaseOutcome::NOT_FOUND)
        ->and($this->lockouts->pairsReleased)->toBe([]);
})->with([
    'a row that does not exist' => ['404'],
    'a failed attempt'          => ['3'],
    'not a number'              => ['abc'],
    'zero'                      => ['0'],
    'a negative number'         => ['-41'],
    'the previous panel id'     => ['0123456789ab-1794560400'],
    'a number with a sign'      => ['+41'],
    'empty'                     => [''],
]);

it('FR-017, User Story 8 scenario 4: releases every active lockout at once, published once with the count', function () {
    $this->lockouts->rows[1] = releaseTestRow(1, $this->sara, $this->office, $this->now->modify('+10 minutes'), 12);
    $this->lockouts->rows[2] = releaseTestRow(2, hash('sha256', 'nobody'), $this->office, $this->now->modify('+3 minutes'));
    $this->lockouts->rows[3] = releaseTestRow(3, hash('sha256', 'old'), $this->office, $this->now->modify('-3 minutes'));
    $published = null;
    Actions\expectDone(LockoutReleaser::RELEASED_ALL)->once()->whenHappen(function (...$args) use (&$published): void {
        $published = $args;
    });

    $outcome = $this->releaser->releaseAll(7, $this->now);

    expect($outcome->status)->toBe(LockoutReleaseOutcome::RELEASED)
        ->and($outcome->released)->toBe(2)
        ->and($published)->toBe([7, 2])
        ->and($this->lockouts->rows[3]->reasonCode)->toBe('threshold_exceeded');
});

it('FR-017, User Story 8 scenario 4: with no active lockout, releasing all says so and records nothing', function () {
    $this->lockouts->rows[3] = releaseTestRow(3, $this->sara, $this->office, $this->now->modify('-3 minutes'));
    Actions\expectDone(LockoutReleaser::RELEASED_ALL)->never();

    $outcome = $this->releaser->releaseAll(7, $this->now);

    expect($outcome->status)->toBe(LockoutReleaseOutcome::NONE_ACTIVE)
        ->and($outcome->released)->toBe(0)
        ->and($this->lockouts->allReleased)->toBe(1);
});
