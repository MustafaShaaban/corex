<?php

/**
 * Approved production logo package contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('defines accessible logo usage and size fixtures independently of assets', function () {
    $fixtures = ThemeContract::json('tests/Fixtures/Branding/logo-usage.json')['scenarios'];

    expect(array_column($fixtures, 'usage'))->toEqualCanonicalizing([
        'decorative', 'named-image', 'linked-brand',
    ]);

    foreach ($fixtures as $fixture) {
        expect($fixture)->toHaveKeys([
            'id', 'usage', 'variant', 'minimum_inline_size_px', 'background', 'accessible_name',
        ])
            ->and($fixture['minimum_inline_size_px'])->toBeGreaterThanOrEqual(16)
            ->and($fixture['background'])->toBeIn(['light', 'dark']);

        if ($fixture['usage'] === 'decorative') {
            expect($fixture['accessible_name'])->toBe('');
        } else {
            expect($fixture['accessible_name'])->not->toBe('');
        }
    }
});

it('requires an owner approved provenance manifest before logo integration', function () {
    $path = ThemeContract::root() . '/plugins/corex-config/assets/brand/logo-manifest.json';
    expect($path)->toBeFile();

    if (! is_file($path)) {
        return;
    }

    $manifest = ThemeContract::json('plugins/corex-config/assets/brand/logo-manifest.json');

    expect($manifest)->toHaveKeys(['source', 'author_or_owner', 'license_or_rights', 'approval_date', 'assets'])
        ->and($manifest['approval_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('uses reusable optimized svg assets for every required variant', function () {
    $path = ThemeContract::root() . '/plugins/corex-config/assets/brand/logo-manifest.json';
    expect($path)->toBeFile();

    if (! is_file($path)) {
        return;
    }

    $manifest = ThemeContract::json('plugins/corex-config/assets/brand/logo-manifest.json');
    $assets = $manifest['assets'] ?? [];
    $variants = array_values(array_unique(array_column($assets, 'variant')));

    expect($assets)->not->toBeEmpty()
        ->and($variants)->toEqualCanonicalizing(['symbol', 'wordmark', 'lockup', 'monochrome', 'contrast']);

    foreach ($assets as $asset) {
        $path = ThemeContract::root() . '/' . $asset['path'];
        expect($path)->toBeFile()->and($path)->toEndWith('.svg');

        $svg = (string) file_get_contents($path);
        expect($svg)->toContain('<svg')->toContain('viewBox=')
            ->not->toMatch('/<(?:script|image|text)\b/i')
            // No external resource dependency. The SVG/xlink namespace literals
            // (www.w3.org/...) are identifiers, never fetched, so they are allowed;
            // any other http(s) URL is a forbidden external dependency.
            ->not->toMatch('#https?://(?!www\.w3\.org/)#i')
            // No font-text dependency: the wordmark ships as outlined vector paths,
            // never as live <text> bound to an installed/remote font.
            ->not->toMatch('/font-family|@font-face|\.woff/i');
    }
});

it('documents accessible usage without imposing product identity on client sites', function () {
    $path = ThemeContract::root() . '/plugins/corex-config/assets/brand/logo-manifest.json';
    expect($path)->toBeFile();

    if (! is_file($path)) {
        return;
    }

    $assets = ThemeContract::json(
        'plugins/corex-config/assets/brand/logo-manifest.json',
    )['assets'] ?? [];

    expect($assets)->not->toBeEmpty();

    foreach ($assets as $asset) {
        expect($asset['accessible_usage'] ?? null)->toBeIn(['decorative', 'named-image', 'linked-brand'])
            ->and($asset['client_site_default'] ?? null)->toBeFalse();
    }
});
