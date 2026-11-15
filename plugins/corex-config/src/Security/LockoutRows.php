<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security;

defined('ABSPATH') || exit;

use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginLockoutReader;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use DateTimeImmutable;

/**
 * The Lockouts panel's rows, from the evidence table: what the screen draws on load and what a
 * release answers with, so the panel is redrawn from the same rows (spec 124, FR-016, FR-017).
 *
 * Identities are stored as hashes by design (LoginAttemptRecord), so there is no name to show.
 * A short fingerprint is enough to tell two lockouts apart and to line one up with its lockout
 * notice, and the account is named only when the attempt matched a real user, which is already
 * recorded. Nothing here is reconstructed or guessed.
 *
 * The fingerprint is the policy's, keyed again with the site key, never a prefix of the stored
 * value: a row stored before spec 124 holds a plain SHA-256, and twelve characters of a plain
 * hash of an address give the address back to anyone who hashes the IPv4 range (FR-020).
 *
 * Each row is one username from one network: `identity` and `network` are its two fingerprints and
 * `account` the account's login when the username was one. `id` is the stored row's number, which a
 * release sends back; it was the identity fingerprint and the end time, which the server could only
 * map back by fingerprinting every row and which two networks locking out one username in the same
 * second shared. `reason` is `released_by_owner` for a lockout released from the screen and
 * `released_by_recovery` for one the recovery command ended, and `released` says either.
 */
final class LockoutRows
{
    public function __construct(
        private readonly LoginLockoutReader $lockouts,
        private readonly LoginProtectionPolicy $policy,
    ) {
    }

    /**
     * @return list<array{id:string,identity:string,network:string,account:string,reason:string,released:bool,active:bool,locked_until:string}>
     */
    public function rows(DateTimeImmutable $now): array
    {
        return array_map(function (LoginAttemptRecord $record) use ($now): array {
            $lockedUntil = $record->lockedUntil;

            return [
                'id' => $record->id === null ? '' : (string) $record->id,
                'identity' => $this->policy->identityFingerprint($record->identityHash),
                'network' => $this->policy->networkFingerprint($record->networkHash),
                'account' => $this->accountName($record->userId),
                'reason' => $record->reasonCode,
                'released' => in_array($record->reasonCode, LoginAttemptRecord::RELEASED, true),
                'active' => $lockedUntil !== null && $lockedUntil > $now,
                'locked_until' => $lockedUntil === null ? '' : gmdate(DATE_ATOM, $lockedUntil->getTimestamp()),
            ];
        }, $this->lockouts->recentLockouts($now));
    }

    /** The account a lockout hit, when the attempt matched a real user. */
    private function accountName(?int $userId): string
    {
        if ($userId === null || $userId < 1) {
            return '';
        }

        $user = get_userdata($userId);

        return $user === false ? '' : $user->user_login;
    }
}
