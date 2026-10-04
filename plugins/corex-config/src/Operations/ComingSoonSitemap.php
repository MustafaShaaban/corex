<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * The sitemap a site in Coming soon publishes (spec 101, FR-019): the home URL and nothing else.
 *
 * A crawler that asks what there is to index is told about the launch page, and about none of the
 * pages being built behind it. It is a `urlset`, not WordPress's index of sub-sitemaps: an index
 * would point at addresses that are redirected while the mode is on.
 *
 * Pure — the home URL is handed in — so the document is checked without WordPress.
 */
final class ComingSoonSitemap
{
    public function xml(string $homeUrl): string
    {
        return '<?xml version="1.0" encoding="UTF-8"?>' . "\n"
            . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '<url><loc>' . htmlspecialchars($homeUrl, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</loc></url>'
            . '</urlset>' . "\n";
    }
}
