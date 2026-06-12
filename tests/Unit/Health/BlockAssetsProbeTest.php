<?php

/**
 * Unit tests for the asset-URL health predicate and the block-assets probe (spec 040). No WordPress.
 *
 * @package Corex\Tests\Unit\Health
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Health\AssetUrlHealth;
use Corex\Health\HealthStatus;
use Corex\Health\Probes\BlockAssetsProbe;

const BASE = 'http://corex.local/wp-content/plugins';

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('treats a clean under-plugins URL as healthy', function () {
    expect((new AssetUrlHealth())->isHealthy(BASE . '/corex-ui/build/blocks/posts/index.js', BASE))->toBeTrue();
});

it('treats a URL embedding a drive letter as unhealthy', function () {
    expect((new AssetUrlHealth())->isHealthy(BASE . '/C:/wamp64/www/corex/addons/corex-ui/index.js', BASE))->toBeFalse();
});

it('treats a URL outside the plugins base or with traversal as unhealthy', function () {
    $health = new AssetUrlHealth();

    expect($health->isHealthy('http://corex.local/wp-content/uploads/x.js', BASE))->toBeFalse()
        ->and($health->isHealthy(BASE . '/corex-ui/../../../etc/x.js', BASE))->toBeFalse();
});

it('treats an empty URL as healthy (nothing to load)', function () {
    expect((new AssetUrlHealth())->isHealthy('', BASE))->toBeTrue();
});

it('reports Good when every block asset URL resolves', function () {
    $probe = new BlockAssetsProbe(
        [['name' => 'corex/posts', 'urls' => [BASE . '/corex-ui/build/blocks/posts/index.js']]],
        BASE,
    );

    expect($probe->run()->status)->toBe(HealthStatus::Good);
});

it('reports Critical naming the offending block when a URL is malformed', function () {
    $probe = new BlockAssetsProbe(
        [
            ['name' => 'corex/posts', 'urls' => [BASE . '/corex-ui/build/blocks/posts/index.js']],
            ['name' => 'corex/hero', 'urls' => [BASE . '/C:/wamp64/www/corex/addons/corex-ui/index.js']],
        ],
        BASE,
    );

    $result = $probe->run();

    expect($result->status)->toBe(HealthStatus::Critical)
        ->and($result->description)->toContain('corex/hero')
        ->and($result->description)->not->toContain('corex/posts');
});
