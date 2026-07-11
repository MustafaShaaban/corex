<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Decision returned by the login-protection policy.
 */
final readonly class LoginProtectionDecision
{
    public function __construct(
        public bool $locked,
        public string $reasonCode,
        public ?DateTimeImmutable $lockedUntil = null,
    ) {
    }
}
