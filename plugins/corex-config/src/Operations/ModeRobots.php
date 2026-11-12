<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * Keeps search engines out of a development or staging site (spec 124, FR-002, DECISIONS #526).
 *
 * In Development and Staging every front-end response says `noindex`: in the page, through
 * WordPress's own robots meta (`wp_robots`), and in an `X-Robots-Tag` header, which also covers
 * what is not a page (a feed, a sitemap, an attachment). The header goes through `wp_headers`, the
 * filter WordPress applies to the headers of every request it answers through its front-end
 * pipeline; the admin and the REST API do not go through it, and neither is a page a search engine
 * is sent to.
 *
 * The mode asked is the one in force ({@see OperationsModeStore::current()}), so a site that
 * inherits Staging from WordPress's environment type is kept out as much as one that declared it.
 *
 * The Reading setting (`blog_public`) is never written, so switching to Production removes the
 * `noindex` with no other step, and the owner's own choice in Settings > Reading is what the live
 * site keeps. Every other mode adds nothing, and takes away nothing WordPress put there itself.
 */
final class ModeRobots
{
    private const KEPT_OUT = [OperationsMode::DEVELOPMENT, OperationsMode::STAGING];

    private const HEADER = 'X-Robots-Tag';

    public function __construct(private readonly OperationsModeStore $store)
    {
    }

    /** Whether this mode keeps search engines out. */
    public static function keepsOut(string $mode): bool
    {
        return in_array($mode, self::KEPT_OUT, true);
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
        if (! $this->keepingOut()) {
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
        if (! $this->keepingOut()) {
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

    private function keepingOut(): bool
    {
        return self::keepsOut($this->store->current());
    }
}
