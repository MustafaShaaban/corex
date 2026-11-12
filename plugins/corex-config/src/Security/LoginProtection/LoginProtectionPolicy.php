<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use Corex\Security\LoginLockoutEvent;
use DateTimeImmutable;

/**
 * Pure login-protection policy helpers.
 *
 * The username and the address of an attempt are kept only as hashes keyed with the site's own
 * secret (spec 124, FR-019). A plain hash of either can be read back by hashing a list of names or
 * every IPv4 address; a keyed one cannot without the key.
 *
 * What is shown of a stored value is a fingerprint keyed again with the same key (FR-020), so a
 * row stored as a plain hash before the update cannot be read back from its fingerprint either.
 */
final readonly class LoginProtectionPolicy
{
    public function __construct(
        public LoginProtectionSettings $settings,
        private LoginHashKey $key,
    ) {
    }

    public function identityHash(LoginProtectionContext $context): string
    {
        return $this->identityHashOf($context->identity);
    }

    public function networkHash(LoginProtectionContext $context): string
    {
        return $this->networkHashOf($context->clientIp);
    }

    /** The keyed value a typed username is recorded under, read as WordPress reads it: trimmed, any case. */
    public function identityHashOf(string $identity): string
    {
        return hash_hmac('sha256', $this->normalized($identity), $this->key->current());
    }

    public function networkHashOf(string $clientIp): string
    {
        return hash_hmac('sha256', $clientIp, $this->key->current());
    }

    /** The values this attempt is counted by: the keyed pair, and the plain pair stored before the update. */
    public function keys(LoginProtectionContext $context): LoginAttemptKeys
    {
        return new LoginAttemptKeys(
            identityHash: $this->identityHash($context),
            networkHash: $this->networkHash($context),
            legacyIdentityHash: $this->legacyIdentityHashOf($context->identity),
            legacyNetworkHash: $this->legacyNetworkHashOf($context->clientIp),
        );
    }

    /** The plain value the release before this one recorded a typed username under. */
    public function legacyIdentityHashOf(string $identity): string
    {
        return hash('sha256', $this->normalized($identity));
    }

    /** The plain value the release before this one recorded an address under. */
    public function legacyNetworkHashOf(string $clientIp): string
    {
        return hash('sha256', $clientIp);
    }

    /**
     * The short fingerprint shown for a stored value, keyed or plain: the first characters of its
     * HMAC with the site key. A prefix of a plain hash would give the address back to anyone who
     * hashed the IPv4 range; this one cannot be checked without the key. The Lockouts panel and the
     * lockout notice both show this, so a notice lines up with its row.
     */
    public function fingerprint(string $storedHash): string
    {
        return substr(hash_hmac('sha256', $storedHash, $this->key->current()), 0, LoginLockoutEvent::FINGERPRINT_LENGTH);
    }

    public function retentionUntil(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('+' . $this->settings->retainDays . ' days');
    }

    private function normalized(string $identity): string
    {
        return mb_strtolower(trim($identity));
    }
}
