<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Immutable login-protection policy settings.
 */
final readonly class LoginProtectionSettings
{
    /**
     * @param list<string> $trustedProxyRanges
     */
    public function __construct(
        public bool $enabled,
        public string $customSlug,
        public bool $blockDefaultEndpoints,
        public int $threshold,
        public int $windowSeconds,
        public int $lockoutSeconds,
        public bool $trustedProxyMode,
        public array $trustedProxyRanges,
        public int $retainDays,
        public bool $successfulLoginLogging,
    ) {
        if ($this->threshold < 1 || $this->windowSeconds < 1 || $this->lockoutSeconds < 1 || $this->retainDays < 1) {
            throw new InvalidArgumentException('Login protection timing and threshold settings must be positive.');
        }

        // Still a throw: constructing this with an unusable slug is a programmer error, and an
        // empty slug is the lockout (no login URL exists). Persisted values can no longer reach
        // here unusable — LoginProtectionSettingsStore resolves every read through LoginSlug —
        // which is what stops a bad option from taking the whole provider down (DECISIONS #140).
        if (! LoginSlug::isValid($this->customSlug)) {
            throw new InvalidArgumentException('Custom login slug is invalid.');
        }
    }
}
