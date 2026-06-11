<?php

/**
 * Unit tests for the WooCommerce kit gate + blueprint. The gate proves the kit is
 * never a hard dependency: it runs only when Woo is active AND the flag is on.
 *
 * @package Corex\Tests\Unit\Woo
 */

declare(strict_types=1);

use Corex\Support\Config\ConfigInterface;
use Corex\Support\Config\FeatureFlags;
use Corex\Woo\WooBlueprint;
use Corex\Woo\WooKitGate;

function flagsWith(bool $kitOn): FeatureFlags
{
    $config = new class($kitOn) implements ConfigInterface {
        public function __construct(private bool $kitOn)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'features.woocommerce_kit' ? $this->kitOn : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'features.woocommerce_kit';
        }
    };

    return new FeatureFlags($config);
}

it('enables the kit only when WooCommerce is active and the flag is on', function () {
    $on = new WooKitGate(flagsWith(true));
    $off = new WooKitGate(flagsWith(false));

    expect($on->isEnabled(true))->toBeTrue();       // Woo active + flag on
    expect($on->isEnabled(false))->toBeFalse();      // Woo inactive → off (never a hard dep)
    expect($off->isEnabled(true))->toBeFalse();      // flag off → off
    expect($off->isEnabled(false))->toBeFalse();
});

it('describes the store kit it provides', function () {
    $kit = new WooBlueprint();

    expect($kit->name())->toBe('woocommerce')
        ->and($kit->requiredModules())->toContain('corex-blocks')
        ->and($kit->templates())->toContain('front-page')
        ->and($kit->patterns())->toContain('corex/hero');
});

it('declares only templates/parts that exist in the theme', function () {
    $themeDir = dirname(__DIR__, 3) . '/theme';
    $kit = new WooBlueprint();

    foreach ($kit->templates() as $template) {
        expect(is_file("{$themeDir}/templates/{$template}.html"))->toBeTrue("template {$template}");
    }
    foreach ($kit->parts() as $part) {
        expect(is_file("{$themeDir}/parts/{$part}.html"))->toBeTrue("part {$part}");
    }
});
