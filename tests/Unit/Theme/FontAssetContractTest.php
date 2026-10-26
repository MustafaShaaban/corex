<?php

/**
 * Font asset, provenance, and loading contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('ships the same four font files in the theme and the core plug-in, and no more', function () {
    // Spec 121, US-BRD-09 (DECISIONS #464): the theme must work without the plug-in, so both keep
    // a copy, and the four-file limit counts both.
    $theme = glob(ThemeContract::root() . '/theme/assets/fonts/*.woff2') ?: [];
    $plugin = glob(ThemeContract::root() . '/plugins/corex-core/assets/fonts/*.woff2') ?: [];
    $digest = static fn (array $files): array => array_combine(
        array_map('basename', $files),
        array_map(static fn (string $file): string => (string) hash_file('sha256', $file), $files),
    );

    expect(count($theme))->toBeLessThanOrEqual(4)
        ->and(count($plugin))->toBeLessThanOrEqual(4)
        ->and($digest($plugin))->toEqual($digest($theme));
});

it('loads no font from an external font CDN', function () {
    $themeFiles = array_merge(
        [ThemeContract::root() . '/theme/theme.json'],
        glob(ThemeContract::root() . '/theme/styles/*.json') ?: [],
    );
    $external = [];

    foreach ($themeFiles as $file) {
        if (preg_match('/https?:\/\/(?:fonts\.|use\.typekit|cdn\.)/i', (string) file_get_contents($file))) {
            $external[] = str_replace(ThemeContract::root() . '/', '', $file);
        }
    }

    expect($external)->toBe([]);
});

it('records provenance roles subsets weights and swap behavior for every font file', function () {
    $path = ThemeContract::root() . '/theme/assets/fonts/manifest.json';
    expect($path)->toBeFile();

    if (! is_file($path)) {
        return;
    }

    $manifest = ThemeContract::json('theme/assets/fonts/manifest.json');
    $records = $manifest['fonts'] ?? [];
    $requiredRoles = ['display-heading', 'code-technical', 'arabic'];

    expect(array_values(array_unique(array_column($records, 'role'))))->toEqualCanonicalizing($requiredRoles);

    foreach ($records as $record) {
        expect($record)->toHaveKeys([
            'family', 'role', 'path', 'weights', 'script_subset', 'license_source', 'checksum',
            'font_display', 'preload',
        ])
            ->and($record['path'])->toEndWith('.woff2')
            ->and($record['font_display'])->toBe('swap');

        $fontPath = ThemeContract::root() . '/' . $record['path'];
        $licensePath = ThemeContract::root() . '/' . $record['license_source'];
        expect($fontPath)->toBeFile()
            ->and($licensePath)->toBeFile()
            ->and($record['checksum'])->toBe('sha256:' . hash_file('sha256', $fontPath));

        if ($record['preload']) {
            expect($record['evidence_id'] ?? null)->not->toBeNull();
        }
    }
});

it('maps every approved file through WordPress font faces without preload', function () {
    $families = ThemeContract::json('theme/theme.json')['settings']['typography']['fontFamilies'];
    $sources = [];

    foreach ($families as $family) {
        foreach ($family['fontFace'] ?? [] as $face) {
            expect($face['fontDisplay'] ?? null)->toBe('swap');
            array_push($sources, ...($face['src'] ?? []));
        }
    }

    expect($sources)->toEqualCanonicalizing([
        'file:./assets/fonts/space-grotesk-latin-500-700.woff2',
        'file:./assets/fonts/jetbrains-mono-latin-400-600.woff2',
        'file:./assets/fonts/ibm-plex-sans-arabic-400.woff2',
        'file:./assets/fonts/ibm-plex-sans-arabic-600.woff2',
    ]);
});

it('defines the approved typography roles with readable fallbacks', function () {
    $families = ThemeContract::json('theme/theme.json')['settings']['typography']['fontFamilies'];
    $bySlug = array_column($families, null, 'slug');

    expect($bySlug)->toHaveKeys(['body', 'heading', 'mono', 'arabic'])
        ->and($bySlug['heading']['fontFamily'])->toContain('Space Grotesk')->toContain('sans-serif')
        ->and($bySlug['mono']['fontFamily'])->toContain('JetBrains Mono')->toContain('monospace')
        ->and($bySlug['arabic']['fontFamily'])->toContain('IBM Plex Sans Arabic')->toContain('sans-serif');
});
