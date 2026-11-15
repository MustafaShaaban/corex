<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Releases a lockout, or every active lockout, from the screen (spec 124, FR-017).
 *
 * One lockout is named by the number of its stored row, which the Lockouts panel sends as the row's
 * `id`. The row gives the identity and network values it was stored under, and every lockout still
 * active under that pair ends: two failures at once can each have started one. The same username
 * from another network is another lockout and is left alone. A lockout stored before spec 124 under
 * the plain pair is its own row on the panel and is released by its own id; the plain pair and the
 * keyed pair of one person cannot be matched without the username and address they hide.
 *
 * A release ends the lockout at the moment it is made and keeps the failed attempts, which stop
 * counting toward the next lockout of the pair ({@see WpLoginAttemptStore::failures()}): otherwise
 * one mistyped password after the release would lock the person out again.
 *
 * What was released is published once, on {@see self::RELEASED} or {@see self::RELEASED_ALL}, with
 * the person who released it, how many rows ended, and the account or the fingerprints, never a
 * stored value. A release that ends nothing publishes nothing.
 */
final class LockoutReleaser
{
    /** One lockout released: the actor's id, rows ended, the account's id (0 for none), the fingerprints. */
    public const RELEASED = 'corex_login_lockout_released';

    /** Every active lockout released: the actor's id and how many rows ended. */
    public const RELEASED_ALL = 'corex_login_lockouts_released';

    public function __construct(
        private readonly LoginLockoutReleases $lockouts,
        private readonly LoginProtectionPolicy $policy,
    ) {
    }

    public function releaseOne(string $id, int $actorId, DateTimeImmutable $now): LockoutReleaseOutcome
    {
        $lockout = preg_match('/^[1-9][0-9]{0,17}$/', $id) === 1 ? $this->lockouts->lockoutById((int) $id) : null;

        if ($lockout === null || $lockout->outcome !== LoginAttemptRecord::LOCKED) {
            return LockoutReleaseOutcome::notFound();
        }

        if ($lockout->lockedUntil === null || $lockout->lockedUntil <= $now) {
            return LockoutReleaseOutcome::alreadyEnded();
        }

        $released = $this->lockouts->releasePair($lockout->identityHash, $lockout->networkHash, $now);

        // Another release, or the lockout's own end, came between the read and the write.
        if ($released === 0) {
            return LockoutReleaseOutcome::alreadyEnded();
        }

        do_action(
            self::RELEASED,
            $actorId,
            $released,
            $lockout->userId ?? 0,
            $this->policy->identityFingerprint($lockout->identityHash),
            $this->policy->networkFingerprint($lockout->networkHash),
        );

        return LockoutReleaseOutcome::released($released);
    }

    public function releaseAll(int $actorId, DateTimeImmutable $now): LockoutReleaseOutcome
    {
        $released = $this->lockouts->releaseAllActive($now);

        if ($released === 0) {
            return LockoutReleaseOutcome::noneActive();
        }

        do_action(self::RELEASED_ALL, $actorId, $released);

        return LockoutReleaseOutcome::released($released);
    }
}
