<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Whether the default login address (`/wp-login.php`, and `/wp-admin` for a visitor) is hidden,
 * decided once (spec 114, US-SAFE-11).
 *
 * The guard hid it only with protection on, hiding on and recovery off, while the Security
 * Center printed the hiding setting alone: every fresh install read "Hidden" with the login form
 * one request away. The guard, the Security Center and the Overview now all ask this.
 */
final class LoginAddressState
{
    /** Login protection is off: the default address answers as WordPress's own. */
    public const OFF = 'off';

    /** Protection is on and does not hide the default address. */
    public const REACHABLE = 'reachable';

    /** Protection is on and hides the default address. */
    public const HIDDEN = 'hidden';

    /** The settings would hide it, and the recovery constant keeps it reachable. */
    public const RECOVERY = 'recovery';

    public static function of(LoginProtectionSettings $settings, bool $recoveryActive): string
    {
        if (! $settings->enabled) {
            return self::OFF;
        }

        if (! $settings->blockDefaultEndpoints) {
            return self::REACHABLE;
        }

        return $recoveryActive ? self::RECOVERY : self::HIDDEN;
    }
}
