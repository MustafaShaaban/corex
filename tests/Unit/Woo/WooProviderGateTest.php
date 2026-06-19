<?php

/**
 * Unit tests for Woo provider runtime exclusion (spec 055 T009).
 *
 * @package Corex\Tests\Unit\Woo
 */

declare(strict_types=1);

use Corex\Foundation\AddonProviderResolver;
use Corex\Foundation\AddonRuntimeState;
use Corex\Tests\Unit\Foundation\AddonProviderFixtures;
use Corex\Woo\WooServiceProvider;

it('excludes the Woo provider when WooCommerce is unavailable', function () {
    $resolver = new AddonProviderResolver([
        AddonProviderFixtures::active(),
        AddonProviderFixtures::wooMissing(),
    ]);

    $resolution = $resolver->resolve(
        [],
        new AddonRuntimeState(
            activeSlugs: ['corex-ui', 'corex-kit-woo'],
            installedPluginFiles: ['corex-ui/corex-ui.php', 'corex-kit-woo/corex-kit-woo.php'],
            enabledFlags: ['woocommerce_kit'],
            externalGates: ['woocommerce' => false],
        ),
    );

    expect($resolution->providerClasses())->not->toContain(WooServiceProvider::class)
        ->and($resolution->reasonFor('corex-kit-woo'))->toBe('external gate unavailable: woocommerce');
});

it('excludes the Woo provider when the kit state is inactive', function () {
    $resolver = new AddonProviderResolver([
        AddonProviderFixtures::active(),
        AddonProviderFixtures::wooMissing(),
    ]);

    $resolution = $resolver->resolve(
        [],
        new AddonRuntimeState(
            activeSlugs: ['corex-ui', 'corex-kit-woo'],
            installedPluginFiles: ['corex-ui/corex-ui.php', 'corex-kit-woo/corex-kit-woo.php'],
            enabledFlags: [],
            externalGates: ['woocommerce' => true],
        ),
    );

    expect($resolution->providerClasses())->not->toContain(WooServiceProvider::class)
        ->and($resolution->reasonFor('corex-kit-woo'))->toBe('feature flag disabled: woocommerce_kit');
});
