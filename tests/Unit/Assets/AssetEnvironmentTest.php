<?php

/**
 * Unit tests for asset environment resolution (spec 047: US2, FR-006/FR-007).
 *
 * @package Corex\Tests\Unit\Assets
 */

declare(strict_types=1);

use Corex\Assets\AssetEnvironment;

it('resolves local/development to local', function () {
    expect(AssetEnvironment::from('local')->name)->toBe(AssetEnvironment::LOCAL)
        ->and(AssetEnvironment::from('development')->name)->toBe(AssetEnvironment::LOCAL)
        ->and(AssetEnvironment::from('DEV')->name)->toBe(AssetEnvironment::LOCAL);
});

it('resolves staging, and defaults everything else to production', function () {
    expect(AssetEnvironment::from('staging')->name)->toBe(AssetEnvironment::STAGING)
        ->and(AssetEnvironment::from('production')->name)->toBe(AssetEnvironment::PRODUCTION)
        ->and(AssetEnvironment::from(null)->name)->toBe(AssetEnvironment::PRODUCTION)
        ->and(AssetEnvironment::from('whatever')->name)->toBe(AssetEnvironment::PRODUCTION);
});

it('exposes source maps only in local', function () {
    expect(AssetEnvironment::from('local')->exposesSourceMaps())->toBeTrue()
        ->and(AssetEnvironment::from('staging')->exposesSourceMaps())->toBeFalse()
        ->and(AssetEnvironment::from('production')->exposesSourceMaps())->toBeFalse();
});
