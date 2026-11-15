<?php

/**
 * The CoreX theme's heading face is preloaded, so a page is drawn in it from the first frame and
 * nothing moves when it arrives (spec 121, T031 and US-BRD-09; spec 116 FR-020; spec 057's
 * evidence-gated preload).
 *
 * At 375px the heading scale's 40px h1 sets "Build something great" on three lines in a Linux
 * system font and on two in Space Grotesk, so the swap moved the page by 0.069 when the budget
 * allows 0.05. Chrome holds the first frame for a preloaded font for a short while, so the face is
 * there before anything is drawn.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Support\ThemeContract;
use Corex\Theme\ThemeDefaultPresets;
use Corex\Theme\ThemeFontPreload;

/** The theme layer's font families as `wp_get_global_settings()` answers for them. */
function theme_font_preload_families(): array
{
    return ThemeContract::json('theme/theme.json')['settings']['typography']['fontFamilies'];
}

/**
 * The preload list the filter answers with, for a theme, a direction and a set of families.
 *
 * @param array<int,array<string,mixed>> $families
 */
function theme_font_preload_for(string $template, bool $rtl, array $families): array
{
    Functions\when('get_template')->justReturn($template);
    Functions\when('is_rtl')->justReturn($rtl);
    Functions\when('wp_get_global_settings')->justReturn($families);
    Functions\when('get_theme_file_uri')->alias(
        static fn (string $file): string => 'https://example.test/wp-content/themes/corex/' . $file,
    );

    return (new ThemeFontPreload())->add([['href' => 'https://example.test/other.css', 'as' => 'style']]);
}

it('preloads the heading face on a left-to-right page of the CoreX theme (spec 121 T031, spec 116 FR-020)', function () {
    $resources = theme_font_preload_for(ThemeDefaultPresets::THEME, false, theme_font_preload_families());

    expect($resources)->toBe([
        ['href' => 'https://example.test/other.css', 'as' => 'style'],
        [
            'href' => 'https://example.test/wp-content/themes/corex/assets/fonts/space-grotesk-latin-500-700.woff2',
            'as' => 'font',
            'type' => 'font/woff2',
            'crossorigin' => 'anonymous',
        ],
    ]);
});

it('preloads nothing for a client theme, whose type the CoreX faces may not draw', function () {
    $resources = theme_font_preload_for('client-theme', false, theme_font_preload_families());

    expect($resources)->toBe([['href' => 'https://example.test/other.css', 'as' => 'style']]);
});

it('preloads nothing on a right-to-left page, where headings are set in the Arabic face (FR-016)', function () {
    // An unused preload is what spec 057 forbids: the theme sets Arabic-script pages' headings,
    // buttons and navigation in IBM Plex Sans Arabic.
    $resources = theme_font_preload_for(ThemeDefaultPresets::THEME, true, theme_font_preload_families());

    expect($resources)->toBe([['href' => 'https://example.test/other.css', 'as' => 'style']]);
});

it('preloads nothing when the heading family has no theme font file of its own', function (array $heading) {
    $resources = theme_font_preload_for(ThemeDefaultPresets::THEME, false, [$heading]);

    expect($resources)->toBe([['href' => 'https://example.test/other.css', 'as' => 'style']]);
})->with([
    'a system stack' => [['slug' => 'heading', 'fontFamily' => 'system-ui, sans-serif']],
    'a face from another address' => [[
        'slug' => 'heading',
        'fontFace' => [['fontFamily' => 'Brand', 'src' => ['https://fonts.example.test/brand.woff2']]],
    ]],
    'a face that is not WOFF2' => [[
        'slug' => 'heading',
        'fontFace' => [['fontFamily' => 'Brand', 'src' => 'file:./assets/fonts/brand.ttf']],
    ]],
]);

it('reads the heading face a brand override puts in the theme layer', function () {
    $resources = theme_font_preload_for(ThemeDefaultPresets::THEME, false, [[
        'slug' => 'heading',
        'fontFace' => [['fontFamily' => 'Brand', 'src' => 'file:./assets/fonts/brand.woff2']],
    ]]);

    expect($resources[1]['href'] ?? null)->toBe('https://example.test/wp-content/themes/corex/assets/fonts/brand.woff2');
});

it('records the Space Grotesk preload in the font manifest with its evidence, and no other', function () {
    $records = ThemeContract::json('theme/assets/fonts/manifest.json')['fonts'];
    $preloaded = array_values(array_filter($records, static fn (array $record): bool => $record['preload'] === true));

    expect(array_column($preloaded, 'path'))->toBe(['theme/assets/fonts/space-grotesk-latin-500-700.woff2'])
        ->and($preloaded[0]['evidence_id'] ?? '')->not->toBe('');
});
