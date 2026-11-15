<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use InvalidArgumentException;

/**
 * Privacy-preserving login attempt evidence.
 *
 * A lockout that was ended before its time keeps why in its reason: `released_by_owner` when someone
 * who manages operations released it from the screen (spec 124, FR-017), `released_by_recovery`
 * when `wp corex security reset-login` did. Its end is then the moment it was released, and the
 * failed attempts before that moment no longer count toward the next lockout of the same pair.
 * `id` is the stored row's number, which the Lockouts panel sends back to name a lockout.
 */
final readonly class LoginAttemptRecord
{
    public const FAILED = 'failed';
    public const LOCKED = 'locked';
    public const SUCCESS = 'success';

    public const RELEASED_BY_OWNER = 'released_by_owner';
    public const RELEASED_BY_RECOVERY = 'released_by_recovery';

    /** The reasons a lockout ended before its time. */
    public const RELEASED = [self::RELEASED_BY_OWNER, self::RELEASED_BY_RECOVERY];

    public function __construct(
        public string $identityHash,
        public string $networkHash,
        public string $outcome,
        public string $reasonCode,
        public ?int $userId,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $retentionUntil,
        public ?DateTimeImmutable $lockedUntil = null,
        public ?int $id = null,
    ) {
        foreach ([$this->identityHash, $this->networkHash] as $hash) {
            if (preg_match('/^[0-9a-f]{64}$/', $hash) !== 1) {
                throw new InvalidArgumentException('Login attempt hashes must be SHA-256.');
            }
        }

        if (! in_array($this->outcome, [self::FAILED, self::LOCKED, self::SUCCESS], true)) {
            throw new InvalidArgumentException('Login attempt outcome is invalid.');
        }
    }
}
