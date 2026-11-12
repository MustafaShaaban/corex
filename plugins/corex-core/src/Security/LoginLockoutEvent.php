<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

use Corex\Events\Event;
use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Dispatched the moment login protection locks an identity out after too many failed attempts, so the
 * Notification Center can alert the security managers without watching the attempts table.
 *
 * It carries the account the typed username belongs to (null for an unknown username), short
 * fingerprints (keyed with the site's own secret) of the values the username and the network are
 * recorded under, the same ones the Lockouts panel shows, and when the lockout lifts. It never carries the typed username or the address (spec 124,
 * FR-020): a listener cannot store what it was never given, and the constructor refuses anything
 * that is not a fingerprint.
 */
final class LoginLockoutEvent implements Event
{
    /** A fingerprint's length: the first characters of a keyed SHA-256 value, in hexadecimal. */
    public const FINGERPRINT_LENGTH = 12;

    public function __construct(
        public readonly ?int $userId,
        public readonly string $identityFingerprint,
        public readonly string $networkFingerprint,
        public readonly DateTimeImmutable $lockedUntil,
    ) {
        if ($this->userId !== null && $this->userId < 1) {
            throw new InvalidArgumentException('A lockout names an account by a positive id, or none.');
        }

        foreach ([$this->identityFingerprint, $this->networkFingerprint] as $fingerprint) {
            if (preg_match('/^[0-9a-f]{' . self::FINGERPRINT_LENGTH . '}$/', $fingerprint) !== 1) {
                throw new InvalidArgumentException('A lockout carries fingerprints, never what was typed or an address.');
            }
        }
    }
}
