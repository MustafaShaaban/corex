<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * Where the login is served from, in one place.
 *
 * The guard rewrites login URLs, the Security Center displays the address, and the save endpoint
 * echoes it back. If those three built the URL separately they would drift, and the failure is
 * nasty: the screen shows an address the site does not answer, which is exactly the moment an owner
 * bookmarks it and signs out.
 */
final class LoginUrl
{
    /**
     * @param string|null $scheme Passed through to home_url(); null keeps the site default.
     */
    public static function forSettings(LoginProtectionSettings $settings, ?string $scheme = null): string
    {
        if (! $settings->enabled) {
            return self::defaultUrl();
        }

        $slug = LoginSlug::orDefault($settings->customSlug);

        // Plain permalinks cannot route a path, so the slug travels as a query flag instead — the
        // same shape LoginRouteGuard matches on the way in.
        if (! get_option('permalink_structure')) {
            return home_url('/', $scheme) . '?' . $slug;
        }

        return user_trailingslashit(home_url('/', $scheme) . $slug);
    }

    /**
     * The stock login, built without the filters that would rewrite it.
     *
     * wp_login_url() is off-limits here: LoginRouteGuard's filters are registered in the same
     * request and would hand back the custom address — which is how the recovery command came to
     * print the very URL it had just disabled (DECISIONS #140).
     */
    public static function defaultUrl(): string
    {
        return rtrim((string) get_option('siteurl'), '/') . '/wp-login.php';
    }
}
