<?php

/**
 * Font asset, provenance, and loading contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('ships no more than four self hosted woff2 files and no external font CDN', function () {
    $fontFiles = glob(ThemeContract::root() . '/theme/assets/fonts/*.woff2') ?: [];
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

    expect(count($fontFiles))->toBeLessThanOrEqual(4)
        ->and($external)->toBe([]);
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

        if ($record['preload']) {
            expect($record['evidence_id'] ?? null)->not->toBeNull();
        }
    }
});

it('defines the approved typography roles with readable fallbacks', function () {
    $families = ThemeContract::json('theme/theme.json')['settings']['typography']['fontFamilies'];
    $bySlug = array_column($families, null, 'slug');

    expect($bySlug)->toHaveKeys(['body', 'heading', 'mono', 'arabic'])
        ->and($bySlug['heading']['fontFamily'])->toContain('Space Grotesk')->toContain('sans-serif')
        ->and($bySlug['mono']['fontFamily'])->toContain('JetBrains Mono')->toContain('monospace')
        ->and($bySlug['arabic']['fontFamily'])->toContain('IBM Plex Sans Arabic')->toContain('sans-serif');
});
