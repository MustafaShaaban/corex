<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Releases;

defined('ABSPATH') || exit;

use Closure;
use WP_Application_Passwords;

/**
 * How the request in hand was signed in, and whether this site lets an Application Password
 * install a release (spec 123, FR-014 and FR-014a; DECISIONS #512).
 *
 * The Releases routes were the screen's alone: a request needed the nonce of a signed-in admin
 * page. A site whose `wp-config.php` defines `COREX_ALLOW_RELEASE_PUSH` as true also lets a command on the
 * owner's machine use them, signed in with an Application Password over HTTPS. The switch is a
 * constant and not a setting, because a setting could be turned on over REST by the very
 * credential it lets in.
 */
final class ReleaseCaller
{
    /** The constant a site's configuration file defines as true to let an Application Password install a release. */
    public const PUSH_ALLOWED_BY = 'COREX_ALLOW_RELEASE_PUSH';

    /** @var Closure(int,string):string */
    private readonly Closure $nameOf;

    /**
     * @param bool                              $pushAllowed Whether the site's configuration lets an Application Password install
     *                                                       a release.
     * @param (Closure(int,string):string)|null $nameOf      The name an account's Application Password goes by, from the account
     *                                                       and the password's UUID; WordPress's own record unless a test gives one.
     */
    public function __construct(private readonly bool $pushAllowed, ?Closure $nameOf = null)
    {
        $this->nameOf = $nameOf ?? self::nameInWordPress(...);
    }

    public static function ofThisSite(): self
    {
        return new self(defined(self::PUSH_ALLOWED_BY) && constant(self::PUSH_ALLOWED_BY) === true);
    }

    public function pushAllowed(): bool
    {
        return $this->pushAllowed;
    }

    /**
     * The name of the Application Password WordPress signed this request in with, or null when
     * it was signed in some other way (a cookie) or not at all.
     */
    public function applicationPassword(): ?string
    {
        $uuid = rest_get_authenticated_app_password();

        if (! is_string($uuid) || $uuid === '') {
            return null;
        }

        return ($this->nameOf)(get_current_user_id(), $uuid);
    }

    /**
     * WordPress keeps Application Passwords off plain HTTP unless the site is a local one, and a
     * plugin can lift that with a filter. A release replaces code, so its routes keep the rule.
     */
    public function overHttps(): bool
    {
        return is_ssl() || wp_get_environment_type() === 'local';
    }

    /** The password's name; its UUID when WordPress no longer has it (it was revoked mid-request). */
    private static function nameInWordPress(int $userId, string $uuid): string
    {
        $password = WP_Application_Passwords::get_user_application_password($userId, $uuid);
        $name     = is_array($password) ? (string) ($password['name'] ?? '') : '';

        return $name !== '' ? $name : $uuid;
    }
}
