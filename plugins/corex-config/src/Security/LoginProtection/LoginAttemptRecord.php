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
 */
final readonly class LoginAttemptRecord
{
    public const FAILED = 'failed';
    public const LOCKED = 'locked';
    public const SUCCESS = 'success';

    public function __construct(
        public string $identityHash,
        public string $networkHash,
        public string $outcome,
        public string $reasonCode,
        public ?int $userId,
        public DateTimeImmutable $occurredAt,
        public DateTimeImmutable $retentionUntil,
        public ?DateTimeImmutable $lockedUntil = null,
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
