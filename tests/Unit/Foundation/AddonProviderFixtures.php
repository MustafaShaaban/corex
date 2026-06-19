<?php

/**
 * Runtime add-on provider fixtures for spec 055 tests.
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

namespace Corex\Tests\Unit\Foundation;

use Corex\Captcha\CaptchaServiceProvider;
use Corex\Careers\CareersServiceProvider;
use Corex\Foundation\AddonProvider;
use Corex\Kit\KitServiceProvider;
use Corex\Ui\UiServiceProvider;
use Corex\Woo\WooServiceProvider;

final class AddonProviderFixtures
{
    public static function active(): AddonProvider
    {
        return new AddonProvider(
            slug: 'corex-ui',
            providerClass: UiServiceProvider::class,
            pluginFile: 'corex-ui/corex-ui.php',
        );
    }

    public static function inactive(): AddonProvider
    {
        return new AddonProvider(
            slug: 'corex-captcha',
            providerClass: CaptchaServiceProvider::class,
            pluginFile: 'corex-captcha/corex-captcha.php',
        );
    }

    public static function dependencyMissing(): AddonProvider
    {
        return new AddonProvider(
            slug: 'corex-kit-company',
            providerClass: KitServiceProvider::class,
            pluginFile: 'corex-kit-company/corex-kit-company.php',
            dependencies: ['corex-ui'],
        );
    }

    public static function notInstalled(): AddonProvider
    {
        return new AddonProvider(
            slug: 'corex-careers',
            providerClass: CareersServiceProvider::class,
            pluginFile: 'corex-careers/corex-careers.php',
        );
    }

    public static function wooMissing(): AddonProvider
    {
        return new AddonProvider(
            slug: 'corex-kit-woo',
            providerClass: WooServiceProvider::class,
            pluginFile: 'corex-kit-woo/corex-kit-woo.php',
            dependencies: ['corex-ui'],
            featureFlag: 'woocommerce_kit',
            externalGate: 'woocommerce',
        );
    }
}
