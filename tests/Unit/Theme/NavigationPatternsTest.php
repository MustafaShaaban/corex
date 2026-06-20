<?php

/**
 * Spec 058 US1 — header-simple pattern + navigation tokens.
 *
 * Verifies the simple-company header pattern ships with the required composition
 * (brand + core navigation with a mobile overlay + a CTA), declares the CoreX +
 * core header categories, carries no raw color/size literals, and that theme.json
 * exposes the three new layout-only custom tokens. (Rendered a11y is browser-gated.)
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

function corexNavThemeDir(): string
{
    return ThemeContract::root() . '/theme';
}

it('ships the simple-company header pattern', function () {
    expect(is_file(corexNavThemeDir() . '/patterns/header-simple.php'))->toBeTrue();
});

it('composes brand, core navigation with a mobile overlay, and a CTA', function () {
    $pattern = (string) file_get_contents(corexNavThemeDir() . '/patterns/header-simple.php');

    expect($pattern)->toContain('wp:navigation')
        ->and($pattern)->toContain('overlayMenu')
        ->and($pattern)->toMatch('/wp:(site-logo|site-title)/')
        ->and($pattern)->toContain('wp:buttons');
});

it('declares the CoreX and core header pattern categories', function () {
    $pattern = (string) file_get_contents(corexNavThemeDir() . '/patterns/header-simple.php');

    expect($pattern)->toMatch('/Slug:\s*corex\/header-simple/')
        ->and($pattern)->toMatch('/Categories:[^\n]*corex/')
        ->and($pattern)->toMatch('/Categories:[^\n]*header/');
});

it('uses no hardcoded colors or pixel sizes in nav patterns', function () {
    $files = glob(corexNavThemeDir() . '/patterns/*.php') ?: [];
    expect($files)->not->toBeEmpty();

    foreach ($files as $file) {
        $php = (string) file_get_contents($file);

        expect($php)->not->toMatch('/#[0-9a-fA-F]{3,6}\b/', "hex color in {$file}")
            ->and($php)->not->toMatch('/:\s*\d+px\b/', "pixel size in {$file}");
    }
});

it('exposes the three new layout-only custom tokens in theme.json', function () {
    $theme = ThemeContract::json('theme/theme.json');
    $custom = $theme['settings']['custom'] ?? [];

    expect($custom['header']['height'] ?? null)->not->toBeNull()
        ->and($custom['header']['heightCompact'] ?? null)->not->toBeNull()
        ->and($custom['nav']['breakpoint'] ?? null)->not->toBeNull();
});

it('adds no new color presets for navigation (reuses M2 tokens)', function () {
    $theme = ThemeContract::json('theme/theme.json');
    $palette = $theme['settings']['color']['palette'] ?? [];
    $slugs = array_column($palette, 'slug');

    // No nav-specific brand colors leaked into the palette.
    expect($slugs)->not->toContain('nav')
        ->and($slugs)->not->toContain('header');
});
