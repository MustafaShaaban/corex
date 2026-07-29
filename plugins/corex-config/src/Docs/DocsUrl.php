<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Docs;

use Corex\Support\Config\ConfigInterface;

defined('ABSPATH') || exit;

/**
 * Resolves a relative Corex docs path (e.g. `/guides/media/`) to an absolute URL for admin
 * links. Add-on/registry docs paths are stored relative so they survive different docs hosts;
 * this resolver is the one place that turns them into a full URL.
 *
 * Why this exists: a relative `href="/guides/media/"` in wp-admin resolves against the *active
 * WordPress site* — i.e. the client/company domain being built — which is never where the Corex
 * framework docs live. This resolver always returns an absolute URL so a docs link can never
 * point at the client site.
 *
 * Resolution order:
 *   1. The configured docs base (`docs.base_url` config key), overridable by the
 *      `corex_docs_base_url` filter — e.g. `http://docs.corex.local` on a WAMP docs vhost.
 *   2. If no base is configured, the framework's canonical docs source on GitHub, so the link
 *      is still absolute and useful out of the box.
 */
final class DocsUrl
{
    /**
     * The framework's published documentation site, and the default when no host is configured.
     *
     * This used to be a GitHub `blob` URL into `docs-app/src/content/docs`, which sent an operator
     * looking for help to raw Markdown with the front matter showing. That was the right answer
     * only while no site existed; one has been published since v0.40.0 (spec 090), and a relative
     * docs path maps onto it directly — `/guides/media/` is a page there.
     *
     * Note the `/corex` segment: it is a GitHub *project* page, served from a repository subpath.
     */
    private const PUBLISHED_DOCS_SITE = 'https://mustafashaaban.github.io/corex';

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    /**
     * Absolute URL for a relative docs path. An empty path yields an empty string (no link).
     * An already-absolute URL (http/https) is returned unchanged.
     */
    public function resolve(string $path): string
    {
        $path = trim($path);

        if ($path === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $path) === 1) {
            return $path;
        }

        $base = $this->base();

        if ($base !== '') {
            return rtrim($base, '/') . '/' . ltrim($path, '/');
        }

        // No docs host configured: the framework's own published site, so the link is absolute and
        // never resolves against the active (client) WordPress domain. The trailing slash matches
        // how the site actually serves its pages.
        return self::PUBLISHED_DOCS_SITE . '/' . trim($path, '/') . '/';
    }

    /**
     * Whether this site names its own docs host, rather than falling back to the framework's.
     *
     * Both answers now produce a real documentation site, so this no longer distinguishes "a site"
     * from "raw source" — it distinguishes *whose* site, which is what a team hosting their own
     * needs to know.
     */
    public function hasConfiguredBase(): bool
    {
        return $this->base() !== '';
    }

    private function base(): string
    {
        $configured = (string) $this->config->get('docs.base_url', '');

        /**
         * Filter the Corex docs base URL used for admin documentation links.
         *
         * @param string $configured The value from the `docs.base_url` config key (may be empty).
         */
        return trim((string) apply_filters('corex_docs_base_url', $configured));
    }
}
