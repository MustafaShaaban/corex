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
 *
 * Each kind of value is hashed in its own domain: the message starts with what it is (`identity`,
 * `network`, and for a fingerprint `identity-fingerprint` or `network-fingerprint`) and a NUL byte.
 * Hashed alike, an address typed as the username made the same value as that address, so typing
 * a suspected address confirmed it through the fingerprints. A prefix on the message was chosen
 * over two keys derived from the site key: it separates the domains as surely, with one key and
 * no extra derivation per attempt. The plain values of the release before keep their old form,
 * since they are only ever compared with rows it stored.
 */
final readonly class LoginProtectionPolicy
{
    private const IDENTITY = 'identity';

    private const NETWORK = 'network';

    private const IDENTITY_FINGERPRINT = 'identity-fingerprint';

    private const NETWORK_FINGERPRINT = 'network-fingerprint';

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
        return $this->keyed(self::IDENTITY, $this->normalized($identity));
    }

    public function networkHashOf(string $clientIp): string
    {
        return $this->keyed(self::NETWORK, $clientIp);
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
     * The short fingerprint shown for a stored identity value, keyed or plain: the first characters
     * of its HMAC with the site key. A prefix of a plain hash would give the value back to anyone
     * who hashed a list of names; this one cannot be checked without the key. The Lockouts panel and
     * the lockout notice both show this, so a notice lines up with its row.
     */
    public function identityFingerprint(string $storedHash): string
    {
        return substr($this->keyed(self::IDENTITY_FINGERPRINT, $storedHash), 0, LoginLockoutEvent::FINGERPRINT_LENGTH);
    }

    /** The short fingerprint shown for a stored network value: see {@see identityFingerprint()}. */
    public function networkFingerprint(string $storedHash): string
    {
        return substr($this->keyed(self::NETWORK_FINGERPRINT, $storedHash), 0, LoginLockoutEvent::FINGERPRINT_LENGTH);
    }

    public function retentionUntil(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('+' . $this->settings->retainDays . ' days');
    }

    private function keyed(string $domain, string $value): string
    {
        return hash_hmac('sha256', $domain . "\0" . $value, $this->key->current());
    }

    private function normalized(string $identity): string
    {
        return mb_strtolower(trim($identity));
    }
}
