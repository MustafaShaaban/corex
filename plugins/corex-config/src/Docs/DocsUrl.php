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
     * The framework's canonical docs source. Relative docs paths map predictably to the
     * docs-app content tree (`/guides/media/` → `…/docs-app/src/content/docs/guides/media.md`),
     * so this is a reliable fallback when no docs host is configured.
     */
    private const GITHUB_DOCS_SOURCE = 'https://github.com/MustafaShaaban/corex/blob/main/docs-app/src/content/docs';

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

        // No docs host configured: map to the GitHub source file so the link is absolute and
        // never resolves against the active (client) WordPress domain.
        return self::GITHUB_DOCS_SOURCE . '/' . trim($path, '/') . '.md';
    }

    /**
     * Whether a docs host is explicitly configured (vs. the GitHub fallback). Lets the UI choose
     * helpful copy ("opens the docs site" vs "opens the docs source on GitHub").
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
