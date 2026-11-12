<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Whether the default login address (`/wp-login.php`) is hidden, decided once (spec 114,
 * US-SAFE-11).
 *
 * The guard hid it only with protection on, hiding on and recovery off, while the Security
 * Center printed the hiding setting alone: every fresh install read "Hidden" with the login form
 * one request away. The guard, the Security Center and the Overview now all ask this.
 *
 * Since spec 124 (FR-013) it follows the "hide wp-login.php" switch; hiding the admin is a switch
 * of its own, which {@see LoginProtectionSettings::$hideAdmin} and the Overview's facts report.
 */
final class LoginAddressState
{
    /** Every login switch is off: the default address answers as WordPress's own. */
    public const OFF = 'off';

    /** A switch is on, and wp-login.php is not hidden. */
    public const REACHABLE = 'reachable';

    /** wp-login.php is hidden. */
    public const HIDDEN = 'hidden';

    /** The settings would hide it, and the recovery constant keeps it reachable. */
    public const RECOVERY = 'recovery';

    public static function of(LoginProtectionSettings $settings, bool $recoveryActive): string
    {
        if (! $settings->enabled) {
            return self::OFF;
        }

        if (! $settings->hideWpLogin) {
            return self::REACHABLE;
        }

        return $recoveryActive ? self::RECOVERY : self::HIDDEN;
    }
}
