<?php

/**
 * Tests the expanded design-token system (spec 033): a richer palette, type scale, spacing
 * scale, shadow presets, and radius tokens — all additive (existing slugs preserved) — plus
 * valid style variations.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

$themeDir = dirname(__DIR__, 3) . '/theme';

/**
 * @return array<string,mixed>
 */
function corex_tokens(string $path): array
{
    expect($path)->toBeFile();
    $decoded = json_decode((string) file_get_contents($path), true);
    expect($decoded)->toBeArray();

    return $decoded;
}

it('expands the palette while keeping the existing slugs', function () use ($themeDir) {
    $slugs = array_column(corex_tokens($themeDir . '/theme.json')['settings']['color']['palette'], 'slug');

    expect($slugs)
        ->toContain('primary', 'accent', 'surface', 'ink')             // existing
        ->toContain('surface-alt', 'border', 'ink-soft')               // new structural
        ->toContain('success', 'warning', 'error', 'info');            // new state colors
});

it('provides a fuller type scale and spacing scale (additive)', function () use ($themeDir) {
    $settings = corex_tokens($themeDir . '/theme.json')['settings'];

    expect(array_column($settings['typography']['fontSizes'], 'slug'))
        ->toContain('sm', 'lg', 'hero')                                 // existing
        ->toContain('xs', 'base', 'xl', '2xl');                         // new
    expect(array_column($settings['spacing']['spacingSizes'], 'slug'))
        ->toContain('30', '50', '80')                                   // existing
        ->toContain('10', '20', '40', '60', '70');                      // new
});

it('adds shadow presets and radius tokens', function () use ($themeDir) {
    $settings = corex_tokens($themeDir . '/theme.json')['settings'];

    expect(array_column($settings['shadow']['presets'], 'slug'))->toContain('sm', 'md', 'lg')
        ->and($settings['custom']['radius'])->toHaveKeys(['sm', 'md', 'lg', 'full']);
});

it('styles the button/link/heading elements with tokens', function () use ($themeDir) {
    $elements = corex_tokens($themeDir . '/theme.json')['styles']['elements'];

    expect($elements)->toHaveKeys(['heading', 'link', 'button'])
        ->and($elements['button']['border']['radius'])->toContain('--wp--custom--radius');
});

it('ships valid style variations (dark + editorial), token-only', function () use ($themeDir) {
    foreach (['dark', 'editorial'] as $variation) {
        $json = corex_tokens($themeDir . '/styles/' . $variation . '.json');
        expect($json['version'])->toBe(3)
            ->and($json['title'])->toBeString()->not->toBeEmpty()
            ->and($json['settings']['color']['palette'])->toBeArray()->not->toBeEmpty();
    }
});
