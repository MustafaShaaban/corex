<?php

/**
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Boot;
use Corex\Config\Addons\AddonManager;
use Corex\Config\Addons\AddonRegistry;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\WpPluginActivationInspector;
use Corex\Support\BootLogger;
use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\FeatureFlags;
use Corex\Support\Config\Sources\DotenvSource;
use Corex\Support\Config\Truthy;

function invokeBootFeatureFlag(string $flag): bool
{
    $method = new ReflectionMethod(Boot::class, 'featureFlagEnabled');
    $method->setAccessible(true);

    return $method->invoke(null, $flag);
}

function replaceBootDotenv(?DotenvSource $dotenv): void
{
    $property = new ReflectionProperty(Boot::class, 'dotenv');
    $property->setAccessible(true);
    $property->setValue(null, $dotenv);
}

function truthyEnv(?string $contents = null): string
{
    $directory = sys_get_temp_dir() . '/corex_truthy_' . uniqid('', true);
    mkdir($directory);

    if ($contents !== null) {
        file_put_contents($directory . '/.env', $contents);
    }

    return $directory;
}

function removeTruthyEnv(string $directory): void
{
    if (is_file($directory . '/.env')) {
        unlink($directory . '/.env');
    }

    rmdir($directory);
}

it('accepts exactly the canonical six truthy value shapes', function () {
    foreach ([true, 1, '1', 'true', 'on', 'yes'] as $value) {
        expect(Truthy::of($value))->toBeTrue();
    }

    expect(Truthy::of(' YES '))->toBeTrue();
});

it('rejects false values and non-canonical numeric shapes', function () {
    foreach ([false, 0, '0', 'off', 'no', '', null, [], 2, 1.0] as $value) {
        expect(Truthy::of($value))->toBeFalse();
    }
});

it('uses the canonical yes value in all three former feature-flag call sites', function () {
    $directory = truthyEnv();

    try {
        replaceBootDotenv(new DotenvSource($directory, new BootLogger(false)));
        Functions\when('get_option')->alias(static function (string $name, mixed $default = false): mixed {
            if ($name === 'active_plugins') {
                return [];
            }

            return str_starts_with($name, 'corex_features_') ? 'yes' : $default;
        });
        Functions\when('is_multisite')->justReturn(false);

        $config = new class implements ConfigInterface {
            public function get(string $key, mixed $default = null): mixed
            {
                return 'yes';
            }

            public function has(string $key): bool
            {
                return true;
            }
        };
        $state = (new AddonManager(
            new AddonRegistry(),
            new WpPluginActivationInspector(new SingleSiteMultisiteContext()),
        ))->state();

        expect((new FeatureFlags($config))->enabled('woocommerce_kit'))->toBeTrue()
            ->and(invokeBootFeatureFlag('woocommerce_kit'))->toBeTrue()
            ->and($state->flagOn('woocommerce_kit'))->toBeTrue();
    } finally {
        replaceBootDotenv(null);
        removeTruthyEnv($directory);
    }
});

it('lets an array-backed dotenv feature value gate boot before database options', function () {
    $directory = truthyEnv("FEATURES_WOOCOMMERCE_KIT=yes\n");

    try {
        replaceBootDotenv(new DotenvSource($directory, new BootLogger(false)));
        Functions\when('get_option')->justReturn('off');
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->justReturn('off');

        expect(invokeBootFeatureFlag('woocommerce_kit'))->toBeTrue();
    } finally {
        replaceBootDotenv(null);
        removeTruthyEnv($directory);
    }
});

it('falls back from an absent site feature option to the multisite network option', function () {
    $directory = truthyEnv();

    try {
        replaceBootDotenv(new DotenvSource($directory, new BootLogger(false)));
        Functions\when('get_option')->justReturn(false);
        Functions\when('is_multisite')->justReturn(true);
        Functions\when('get_site_option')->alias(
            static fn (string $name, mixed $default = false): mixed => $name === 'corex_features_woocommerce_kit'
                ? 'yes'
                : $default,
        );

        expect(invokeBootFeatureFlag('woocommerce_kit'))->toBeTrue();
    } finally {
        replaceBootDotenv(null);
        removeTruthyEnv($directory);
    }
});
