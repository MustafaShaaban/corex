<?php

/**
 * Unit tests for first-party add-on provider metadata (spec 055 T004).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Corex\Captcha\CaptchaServiceProvider;
use Corex\Foundation\AddonProvider;
use Corex\Foundation\AddonProviderRegistry;
use Corex\Ui\UiServiceProvider;
use Corex\Woo\WooServiceProvider;

it('exposes the provider metadata needed before optional add-ons boot', function () {
    $provider = new AddonProvider(
        slug: 'corex-kit-woo',
        providerClass: WooServiceProvider::class,
        pluginFile: 'corex-kit-woo/corex-kit-woo.php',
        dependencies: ['corex-ui'],
        featureFlag: 'woocommerce_kit',
        externalGate: 'woocommerce',
    );

    expect($provider->slug)->toBe('corex-kit-woo')
        ->and($provider->providerClass)->toBe(WooServiceProvider::class)
        ->and($provider->pluginFile)->toBe('corex-kit-woo/corex-kit-woo.php')
        ->and($provider->dependencies)->toBe(['corex-ui'])
        ->and($provider->featureFlag)->toBe('woocommerce_kit')
        ->and($provider->externalGate)->toBe('woocommerce')
        ->and($provider->hasFeatureFlag())->toBeTrue()
        ->and($provider->hasExternalGate())->toBeTrue();
});

it('lists first-party optional providers and finds them by slug', function () {
    $registry = new AddonProviderRegistry();

    expect($registry->find('corex-ui')?->providerClass)->toBe(UiServiceProvider::class)
        ->and($registry->find('corex-captcha')?->providerClass)->toBe(CaptchaServiceProvider::class)
        ->and($registry->find('corex-kit-woo')?->providerClass)->toBe(WooServiceProvider::class)
        ->and($registry->find('corex-kit-woo')?->dependencies)->toContain('corex-ui')
        ->and($registry->find('corex-kit-woo')?->featureFlag)->toBe('woocommerce_kit')
        ->and($registry->find('corex-kit-woo')?->externalGate)->toBe('woocommerce')
        ->and($registry->find('not-an-addon'))->toBeNull();
});

