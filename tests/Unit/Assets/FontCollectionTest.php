<?php

/**
 * Curated CoreX font collection for the WP 7 Font Library (spec 062, Priority 2). The definition is pure —
 * it maps the framework's self-hosted brand woff2 into the WordPress Font Library shape (families + faces +
 * categories), with each face `src` resolved against the given fonts base URL.
 *
 * @package Corex\Tests\Unit\Assets
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Assets\FontCollection;

beforeEach(function () {
    Functions\when('__')->returnArg(1);
});

it('defines the three OFL brand families with self-hosted woff2 sources', function () {
    $def = (new FontCollection('https://acme.local/wp-content/plugins/corex-core/assets/fonts'))->definition();

    expect($def)->toHaveKeys(['name', 'description', 'font_families', 'categories'])
        ->and($def['name'])->toBe('CoreX')
        ->and($def['font_families'])->toHaveCount(3);

    $bySlug = [];
    foreach ($def['font_families'] as $family) {
        $bySlug[$family['font_family_settings']['slug']] = $family;
    }

    expect(array_keys($bySlug))->toEqualCanonicalizing(['space-grotesk', 'ibm-plex-sans-arabic', 'jetbrains-mono'])
        // Space Grotesk: one face, weight 500 700, sans-serif, self-hosted woff2 src
        ->and($bySlug['space-grotesk']['categories'])->toBe(['sans-serif'])
        ->and($bySlug['space-grotesk']['font_family_settings']['fontFace'][0]['fontWeight'])->toBe('500 700')
        ->and($bySlug['space-grotesk']['font_family_settings']['fontFace'][0]['src'])
            ->toBe('https://acme.local/wp-content/plugins/corex-core/assets/fonts/space-grotesk-latin-500-700.woff2')
        // IBM Plex Sans Arabic: two faces (400 + 600)
        ->and($bySlug['ibm-plex-sans-arabic']['font_family_settings']['fontFace'])->toHaveCount(2)
        // JetBrains Mono: monospace
        ->and($bySlug['jetbrains-mono']['categories'])->toBe(['monospace'])
        ->and($bySlug['jetbrains-mono']['font_family_settings']['fontFace'][0]['src'])->toEndWith('jetbrains-mono-latin-400-600.woff2');
});

it('uses font-display: swap and a trailing-slash-safe base', function () {
    // base given WITHOUT a trailing slash → still a single slash before the file
    $def = (new FontCollection('https://acme.local/fonts'))->definition();
    $faces = $def['font_families'][0]['font_family_settings']['fontFace'];

    expect($faces[0]['fontDisplay'])->toBe('swap')
        ->and($faces[0]['src'])->toBe('https://acme.local/fonts/space-grotesk-latin-500-700.woff2');
});
