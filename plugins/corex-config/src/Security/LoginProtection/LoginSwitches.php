<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * The three login switches (spec 124, FR-013): hide `wp-login.php`, hide the admin from visitors who
 * are not signed in, limit failed sign-ins. Each acts on its own, and the site's own login address
 * works whenever any of them is on.
 *
 * The release before stored two keys instead: `enabled` (protection on: the own address served and
 * attempts limited) and `block_default_endpoints` (with `enabled`, `wp-login.php` and the admin
 * hidden together). This class is the one place that maps between the two (FR-014): how those keys
 * read as switches, and how the switches are written back into them so a site that later runs an
 * older CoreX gets the closest thing that hides nothing the owner did not ask to hide.
 *
 * | Switches (login, admin, limits) | `enabled` | `block_default_endpoints` | What an older CoreX does |
 * |---|---|---|---|
 * | none                    | false | false | nothing, as asked |
 * | limits only             | true  | false | limits, own address beside `wp-login.php`, as asked |
 * | login only              | true  | false | own address and `wp-login.php` open; adds limits |
 * | admin only              | true  | false | own address and `wp-login.php` open, admin not hidden; adds limits |
 * | login, limits           | true  | false | as above; `wp-login.php` not hidden |
 * | admin, limits           | true  | false | as above; the admin not hidden |
 * | login, admin            | true  | true  | hides both, as asked; adds limits |
 * | all three               | true  | true  | all three, as asked |
 *
 * An older CoreX cannot hide one address without the other, so it hides only where both were asked
 * for. It serves the own address whenever any switch is on, because that is the address the owner
 * signs in at; limits come with it there, and limits hide nothing.
 */
final readonly class LoginSwitches
{
    public function __construct(
        public bool $hideWpLogin,
        public bool $hideAdmin,
        public bool $limitAttempts,
    ) {
    }

    public static function off(): self
    {
        return new self(false, false, false);
    }

    /**
     * The previous release's two keys, in their meaning: protection on with hiding on is all three on;
     * protection on without hiding is limits on, with the own address beside `wp-login.php`; protection
     * off is all three off, whatever "hide" was stored as.
     */
    public static function fromPreviousRelease(bool $enabled, bool $blockDefaultEndpoints): self
    {
        if (! $enabled) {
            return self::off();
        }

        return new self($blockDefaultEndpoints, $blockDefaultEndpoints, true);
    }

    /** Whether any switch is on, which is when the site's own login address works. */
    public function any(): bool
    {
        return $this->hideWpLogin || $this->hideAdmin || $this->limitAttempts;
    }

    /** The previous release's `enabled` key, as written for an older CoreX: see the table above. */
    public function previousEnabled(): bool
    {
        return $this->any();
    }

    /** The previous release's `block_default_endpoints` key, as written for an older CoreX: see the table above. */
    public function previousBlockDefaultEndpoints(): bool
    {
        return $this->hideWpLogin && $this->hideAdmin;
    }
}
