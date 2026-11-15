<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Environment\SiteEnvironment;

/**
 * Keeps search engines out of a development or staging site (spec 124, FR-002, DECISIONS #526 and
 * #625).
 *
 * On such a site every front-end response says `noindex`: in the page, through WordPress's own
 * robots meta (`wp_robots`), and in an `X-Robots-Tag` header, which also covers what is not an HTML
 * page (a feed, a sitemap, an attachment page). The header goes through `wp_headers`, the filter
 * WordPress applies to the headers of every request it answers through its front-end pipeline;
 * the admin and the REST API do not go through it, and neither is a page a search engine is sent
 * to. A file the web server serves straight from `wp-content/uploads` never reaches WordPress, so
 * it gets neither.
 *
 * The question is the site's environment ({@see SiteEnvironment::current()}), not its operations
 * mode, which is the same answer the mail gate gives, so a site never says "staging" for its mail
 * and "production" to search engines (spec 113). A site whose environment is locked to development
 * or staging (`APP_ENV`, the network, the `corex_app_env` option) keeps `noindex` in every mode,
 * Coming soon, Forms paused and Maintenance included. Without a lock the stored mode decides, and a
 * visitor mode is production there: an environment inherited from WordPress's environment type
 * keeps `noindex` only while no mode is stored, and a declared Development or Staging always does.
 *
 * The Reading setting (`blog_public`) is never written, so going live removes the `noindex` with
 * no other step, and the owner's own choice in Settings > Reading is what the live site keeps. A
 * production site adds nothing, and takes away nothing WordPress put there itself.
 */
final class ModeRobots
{
    private const KEPT_OUT = [SiteEnvironment::DEVELOPMENT, SiteEnvironment::STAGING];

    private const HEADER = 'X-Robots-Tag';

    public function __construct(private readonly SiteEnvironment $environment)
    {
    }

    /** Whether search engines are kept out of this site now. */
    public function keepsOut(): bool
    {
        return in_array($this->environment->current()->environment, self::KEPT_OUT, true);
    }

    public function register(): void
    {
        // After WordPress's own robots callbacks (priority 10), so nothing they set is lost.
        add_filter('wp_robots', [$this, 'robots'], 20);
        add_filter('wp_headers', [$this, 'headers']);
    }

    /**
     * @param array<string,bool|string> $robots
     *
     * @return array<string,bool|string>
     */
    public function robots(array $robots): array
    {
        if (! $this->keepsOut()) {
            return $robots;
        }

        $robots['noindex'] = true;

        return $robots;
    }

    /**
     * @param array<string,string> $headers
     *
     * @return array<string,string>
     */
    public function headers(array $headers): array
    {
        if (! $this->keepsOut()) {
            return $headers;
        }

        $existing = trim((string) ($headers[self::HEADER] ?? ''));

        if ($existing === '') {
            $headers[self::HEADER] = 'noindex';
        } elseif (! in_array('noindex', array_map('trim', explode(',', strtolower($existing))), true)) {
            $headers[self::HEADER] = $existing . ', noindex';
        }

        return $headers;
    }
}
