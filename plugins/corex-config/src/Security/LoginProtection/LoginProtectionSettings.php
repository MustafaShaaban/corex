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

        if ($this->customSlug !== '' && preg_match('/^[a-z0-9][a-z0-9-]{2,80}$/', $this->customSlug) !== 1) {
            throw new InvalidArgumentException('Custom login slug is invalid.');
        }
    }
}
