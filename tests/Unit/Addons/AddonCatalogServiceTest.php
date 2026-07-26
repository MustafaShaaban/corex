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

it('splits the catalog into mutually exclusive, exhaustive state buckets', function () {
    $views = [
        catalogView('corex-ui', true, true),          // active
        catalogView('corex-email', true, false),      // installed, not running
        catalogView('corex-kit-woo', false, false),   // not on disk
    ];

    $service = new AddonCatalogService();

    expect(array_map(fn ($v) => $v->addon->slug, $service->filter($views, AddonCatalogService::FILTER_ACTIVE)))
        ->toBe(['corex-ui'])
        ->and(array_map(fn ($v) => $v->addon->slug, $service->filter($views, AddonCatalogService::FILTER_INACTIVE)))
        ->toBe(['corex-email'])
        ->and(array_map(fn ($v) => $v->addon->slug, $service->filter($views, AddonCatalogService::FILTER_NOT_INSTALLED)))
        ->toBe(['corex-kit-woo'])
        ->and($service->filter($views, AddonCatalogService::FILTER_ALL))->toHaveCount(3);

    // Exhaustive and non-overlapping: the three buckets account for every view exactly once.
    $counts = $service->counts($views);
    expect($counts[AddonCatalogService::FILTER_ACTIVE]
        + $counts[AddonCatalogService::FILTER_INACTIVE]
        + $counts[AddonCatalogService::FILTER_NOT_INSTALLED])->toBe($counts[AddonCatalogService::FILTER_ALL]);
});

it('treats an installed-but-not-running add-on as inactive, not as active', function () {
    // A feature-flagged add-on whose flag is off is active as a *plugin* but is not running, and
    // its card is badged accordingly. Filtering by Active must agree with that badge — keying on
    // AddonView::active instead would return a card the screen shows as "Feature off".
    $flagged = new AddonView(
        new Addon('corex-blog-pro', 'corex-blog-pro/corex-blog-pro.php', 'Blog Pro', 'blog_pro'),
        installed: true,
        active: true,
        flagOn: false,
    );

    $service = new AddonCatalogService();

    expect($service->filter([$flagged], AddonCatalogService::FILTER_ACTIVE))->toBe([])
        ->and($service->filter([$flagged], AddonCatalogService::FILTER_INACTIVE))->toHaveCount(1);
});

it('shows the whole catalog rather than hiding it when the filter is unknown', function () {
    // A bad query string must not render an empty grid that reads as "this site has no add-ons".
    $views = [catalogView('corex-ui', true, true), catalogView('corex-email', true, false)];

    expect((new AddonCatalogService())->filter($views, 'nonsense'))->toHaveCount(2);
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
