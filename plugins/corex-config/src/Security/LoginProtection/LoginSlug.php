<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

/**
 * The rules a custom login slug must satisfy, in one place.
 *
 * These rules previously lived in three places that disagreed: the settings store sanitised on
 * write but not on read, the settings value object threw on a slug the store happily produced,
 * and the REST controller validated nothing at all. Each disagreement was a reachable way to lock
 * an owner out of their own site — both were reproduced on a real install (DECISIONS #140).
 * Anything deciding whether a slug is usable MUST come through here.
 */
final class LoginSlug
{
    public const DEFAULT = 'corex-login';

    public const REASON_EMPTY = 'empty';

    public const REASON_RESERVED = 'reserved';

    public const REASON_FORMAT = 'format';

    private const PATTERN = '/^[a-z0-9][a-z0-9-]{2,80}$/';

    /**
     * Slugs that would collide with WordPress itself.
     *
     * `login`, `dashboard`, and `admin` are here because core's `wp_redirect_admin_locations`
     * redirects them to wp-login.php. The guard removes that redirect (it leaks the slug), so
     * taking one of these as the slug would mean the login lives at a path core also claims.
     */
    private const RESERVED = [
        'wp-admin',
        'wp-login',
        'wp-content',
        'wp-includes',
        'wp-json',
        'wp-signup',
        'wp-activate',
        'wp-cron',
        'xmlrpc',
        'login',
        'admin',
        'dashboard',
        'feed',
        'rss',
        'rss2',
        'atom',
        'embed',
        'trackback',
        'page',
        'comments',
        'author',
        'search',
        'index',
    ];

    public static function isValid(string $slug): bool
    {
        return self::rejectionReason($slug) === null;
    }

    /**
     * Why this slug is unusable, or null when it is fine.
     *
     * Returns a machine-readable code, never a translated string. The settings store resolves
     * slugs on `plugins_loaded` (the container rebuilds the settings on every make()), which is
     * long before `init` — calling __() here makes WordPress emit a "textdomain triggered too
     * early" notice on every request. Translation belongs to whatever shows the message to a
     * person; see SecuritySettingsController.
     *
     * @return self::REASON_*|null
     */
    public static function rejectionReason(string $slug): ?string
    {
        if ($slug === '') {
            return self::REASON_EMPTY;
        }

        if (in_array($slug, self::RESERVED, true)) {
            return self::REASON_RESERVED;
        }

        if (preg_match(self::PATTERN, $slug) !== 1) {
            return self::REASON_FORMAT;
        }

        return null;
    }

    /**
     * The slug to actually use — never an unusable one.
     *
     * Read and write both route through this, so a stored value always resolves to the same
     * working slug it was saved as (FR-010). Returning the default rather than an empty string
     * is deliberate: an empty slug means "no login URL exists", which is the lockout.
     */
    public static function orDefault(string $candidate): string
    {
        return self::isValid($candidate) ? $candidate : self::DEFAULT;
    }
}
