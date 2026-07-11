<?php

/**
 * Unit tests for the pure add-on catalog projection (spec 068: T176). No WordPress.
 * Contract: real active/installed/site-kit counts, honest untracked updates, and a
 * truthful missing-package installation path — never a fabricated update or count.
 *
 * @package Corex\Tests\Unit\Addons
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Addons\Addon;
use Corex\Config\Addons\AddonCatalogService;
use Corex\Config\Addons\AddonView;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/**
 * @return AddonView
 */
function catalogView(string $slug, bool $installed, bool $active): AddonView
{
    return new AddonView(
        new Addon($slug, $slug . '/' . $slug . '.php', ucfirst($slug)),
        $installed,
        $active,
        $active,
    );
}

it('counts active, installed, total, and site kits from real views, updates untracked', function () {
    $views = [
        catalogView('corex-ui', true, true),
        catalogView('corex-email', true, false),
        catalogView('corex-kit-company', true, true),
        catalogView('corex-kit-portfolio', false, false),
    ];

    $summary = (new AddonCatalogService())->summary($views);

    expect($summary)->toBe([
        'active'         => 2,
        'installed'      => 3,
        'total'          => 4,
        'siteKits'       => 2,
        'updatesTracked' => false,
    ]);
});

it('lists only not-installed add-ons with a real installation path, never a fabricated update', function () {
    $service = new AddonCatalogService();

    $none = $service->missingPackages([
        catalogView('corex-ui', true, true),
        catalogView('corex-email', true, false),
    ]);
    expect($none)->toBe([]);

    $missing = $service->missingPackages([
        catalogView('corex-ui', true, true),
        catalogView('corex-kit-woo', false, false),
    ]);
    expect($missing)->toHaveCount(1)
        ->and($missing[0]['slug'])->toBe('corex-kit-woo')
        ->and($missing[0]['label'])->toBe('Corex-kit-woo')
        ->and($missing[0]['guidance'])->toContain('corex-kit-woo');
});
