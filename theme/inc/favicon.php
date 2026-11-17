<?php

/**
 * The CoreX favicon, linked by the theme when the site has no site icon (spec 134, FR-022, SC-007).
 *
 * The files are the theme's own copies of the logo set's favicon (DECISIONS #637): the symbol on a
 * graphite tile, as SVG and at 16, 32, 180 and 512px. A site icon chosen in WordPress always wins:
 * WordPress links it itself, and the theme then links nothing. Decorative: a favicon has no name.
 * Presentation only.
 *
 * @package Corex
 */

defined('ABSPATH') || exit;

if (! function_exists('corex_theme_favicon_links')) {
    /**
     * Prints the CoreX favicon links into the head, unless the site has a site icon.
     */
    function corex_theme_favicon_links(): void
    {
        if (has_site_icon()) {
            return;
        }

        $base = get_template_directory_uri() . '/assets/favicon/';
        $links = [
            ['icon', 'corex-favicon.svg', '', 'image/svg+xml'],
            ['icon', 'corex-favicon-32.png', '32x32', 'image/png'],
            ['icon', 'corex-favicon-16.png', '16x16', 'image/png'],
            ['icon', 'corex-favicon-512.png', '512x512', 'image/png'],
            ['apple-touch-icon', 'corex-favicon-180.png', '', ''],
        ];

        foreach ($links as [$rel, $file, $sizes, $type]) {
            printf(
                '<link rel="%s" href="%s"%s%s />' . "\n",
                esc_attr($rel),
                esc_url($base . $file),
                $sizes === '' ? '' : ' sizes="' . esc_attr($sizes) . '"',
                $type === '' ? '' : ' type="' . esc_attr($type) . '"',
            );
        }
    }
}
