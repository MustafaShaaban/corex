<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

defined('ABSPATH') || exit;

/**
 * Preloads the CoreX theme's heading face, so a page is drawn in it from the first frame (spec 121,
 * T031 and US-BRD-09; spec 116 FR-020).
 *
 * Headings, buttons and navigation are set in the heading face with `font-display: swap`. Its file
 * is asked for only once the page is laid out, so the first frame used the system font, and at
 * 375px the 40px h1 of the heading scale wraps onto one more line in a Linux system font than in
 * Space Grotesk: the swap moved the page by more than the 0.05 the front-end budget allows.
 * Chrome holds the first frame for a preloaded font for a short while, so the face is there before
 * anything is drawn. Spec 057 allows a preload with measured evidence; the font manifest names it.
 *
 * Only on the CoreX theme, and only left to right: the theme sets an Arabic-script page's headings
 * in the Arabic face, where a preload of this one would go unused.
 */
final class ThemeFontPreload
{
    /** The font family slug whose first face is preloaded. */
    public const FAMILY = 'heading';

    /** WordPress's placeholder for a font file in the active theme. */
    private const THEME_FILE = 'file:./';

    /**
     * The `wp_preload_resources` filter: the heading face added to the page head's preloads.
     *
     * @param array<int,array<string,string>> $resources
     * @return array<int,array<string,string>>
     */
    public function add(array $resources): array
    {
        if (get_template() !== ThemeDefaultPresets::THEME || is_rtl()) {
            return $resources;
        }

        $file = self::file((array) wp_get_global_settings(['typography', 'fontFamilies', 'theme']));

        if ($file === '') {
            return $resources;
        }

        $resources[] = [
            'href' => get_theme_file_uri($file),
            'as' => 'font',
            'type' => 'font/woff2',
            'crossorigin' => 'anonymous',
        ];

        return $resources;
    }

    /**
     * The heading family's first face, as a path in the theme, or '' when it is not a WOFF2 file
     * of the theme's own.
     *
     * @param array<int,array<string,mixed>> $families The theme layer's font families.
     */
    public static function file(array $families): string
    {
        foreach ($families as $family) {
            if (($family['slug'] ?? '') !== self::FAMILY) {
                continue;
            }

            $source = (array) ($family['fontFace'][0]['src'] ?? []);
            $first = is_string($source[0] ?? null) ? $source[0] : '';

            if (! str_starts_with($first, self::THEME_FILE) || ! str_ends_with($first, '.woff2')) {
                return '';
            }

            return substr($first, strlen(self::THEME_FILE));
        }

        return '';
    }
}
