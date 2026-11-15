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
 *
 * The three switches ({@see LoginSwitches}, spec 124 FR-013) are what the guard and the limits act
 * on. Built from the previous release's two keys alone (`enabled`, `blockDefaultEndpoints`, which
 * is how settings that release stored are read, FR-014), the switches are those keys in their old
 * meaning; built with switches, the two keys are what is written for an older CoreX. Either way
 * `enabled` is true exactly when a switch is on, and the own login address works then.
 */
final readonly class LoginProtectionSettings
{
    /** Hide `wp-login.php` (and the addresses that only forward to it) from everyone. */
    public bool $hideWpLogin;

    /** Answer the admin area as a missing page for a visitor who is not signed in. */
    public bool $hideAdmin;

    /** Count failed sign-ins and lock out at the threshold. */
    public bool $limitAttempts;

    /** The previous release's "protection on" key: on whenever any switch is. */
    public bool $enabled;

    /**
     * The previous release's "hide" key, as stored or as written for an older CoreX. It decides
     * nothing here: the switches do.
     */
    public bool $blockDefaultEndpoints;

    /**
     * @param list<string> $trustedProxyRanges
     */
    public function __construct(
        bool $enabled,
        public string $customSlug,
        bool $blockDefaultEndpoints,
        public int $threshold,
        public int $windowSeconds,
        public int $lockoutSeconds,
        public bool $trustedProxyMode,
        public array $trustedProxyRanges,
        public int $retainDays,
        public bool $successfulLoginLogging,
        ?LoginSwitches $switches = null,
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

        if ($switches !== null
            && ($enabled !== $switches->previousEnabled() || $blockDefaultEndpoints !== $switches->previousBlockDefaultEndpoints())
        ) {
            throw new InvalidArgumentException('The previous release\'s login keys contradict the switches; build these settings with of().');
        }

        $switches ??= LoginSwitches::fromPreviousRelease($enabled, $blockDefaultEndpoints);

        $this->hideWpLogin = $switches->hideWpLogin;
        $this->hideAdmin = $switches->hideAdmin;
        $this->limitAttempts = $switches->limitAttempts;
        $this->enabled = $enabled;
        $this->blockDefaultEndpoints = $blockDefaultEndpoints;
    }

    /**
     * Settings built from the three switches, with the previous release's keys written as
     * {@see LoginSwitches} says. The defaults are the documented ones (spec 124, User Story 7).
     *
     * @param list<string> $trustedProxyRanges
     */
    public static function of(
        LoginSwitches $switches,
        string $customSlug = LoginSlug::DEFAULT,
        int $threshold = 5,
        int $windowSeconds = 300,
        int $lockoutSeconds = 900,
        bool $trustedProxyMode = false,
        array $trustedProxyRanges = [],
        int $retainDays = 30,
        bool $successfulLoginLogging = true,
    ): self {
        return new self(
            enabled: $switches->previousEnabled(),
            customSlug: $customSlug,
            blockDefaultEndpoints: $switches->previousBlockDefaultEndpoints(),
            threshold: $threshold,
            windowSeconds: $windowSeconds,
            lockoutSeconds: $lockoutSeconds,
            trustedProxyMode: $trustedProxyMode,
            trustedProxyRanges: $trustedProxyRanges,
            retainDays: $retainDays,
            successfulLoginLogging: $successfulLoginLogging,
            switches: $switches,
        );
    }

    /** The three switches, as one value. */
    public function switches(): LoginSwitches
    {
        return new LoginSwitches($this->hideWpLogin, $this->hideAdmin, $this->limitAttempts);
    }
}
