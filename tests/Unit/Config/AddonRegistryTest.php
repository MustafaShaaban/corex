<?php

/**
 * Unit tests for the add-on registry (spec 026 US1: FR-001/007, SC-005). Pure — no WordPress.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Config\Addons\Addon;
use Corex\Config\Addons\AddonRegistry;
use Corex\Config\Addons\AddonTier;
use Corex\Foundation\AddonProviderRegistry;

it('lists the known Corex add-ons with their plugin files', function () {
    $slugs = array_map(static fn (Addon $a): string => $a->slug, (new AddonRegistry())->all());

    expect($slugs)->toContain('corex-ui', 'corex-email', 'corex-kit-company', 'corex-kit-woo')
        ->and((new AddonRegistry())->find('corex-ui')?->pluginFile)->toBe('corex-ui/corex-ui.php');
});

it('declares the kit -> corex-ui dependency', function () {
    $registry = new AddonRegistry();

    expect($registry->find('corex-kit-company')?->requires)->toContain('corex-ui')
        ->and($registry->find('corex-kit-portfolio')?->requires)->toContain('corex-ui')
        ->and($registry->find('corex-ui')?->requires)->toBe([]);
});

it('marks the woo kit with its feature flag', function () {
    expect((new AddonRegistry())->find('corex-kit-woo')?->flag)->toBe('woocommerce_kit')
        ->and((new AddonRegistry())->find('corex-ui')?->hasFlag())->toBeFalse();
});

it('returns null for an unknown slug', function () {
    expect((new AddonRegistry())->find('not-an-addon'))->toBeNull();
});

it('classifies add-ons into company-site tiers (recommended / optional / kit / woo)', function () {
    $registry = new AddonRegistry();

    expect($registry->find('corex-ui')?->tier)->toBe(AddonTier::Recommended)
        ->and($registry->find('corex-media')?->tier)->toBe(AddonTier::Recommended)
        ->and($registry->find('corex-kit-company')?->tier)->toBe(AddonTier::Recommended)
        ->and($registry->find('corex-captcha')?->tier)->toBe(AddonTier::Optional)
        ->and($registry->find('corex-newsletter')?->tier)->toBe(AddonTier::Optional)
        ->and($registry->find('corex-kit-portfolio')?->tier)->toBe(AddonTier::SiteKit)
        ->and($registry->find('corex-kit-woo')?->tier)->toBe(AddonTier::RequiresWooCommerce);
});

it('does not list the always-on framework foundation as toggleable add-ons', function () {
    $slugs = array_map(static fn (Addon $a): string => $a->slug, (new AddonRegistry())->all());

    expect($slugs)
        ->not->toContain('corex-core')
        ->not->toContain('corex-blocks')
        ->not->toContain('corex-config')
        ->not->toContain('corex-forms');
});

it('mirrors shared runtime provider metadata', function () {
    $configRegistry = new AddonRegistry();

    foreach ((new AddonProviderRegistry())->all() as $provider) {
        $addon = $configRegistry->find($provider->slug);

        expect($addon)->not->toBeNull()
            ->and($addon->pluginFile)->toBe($provider->pluginFile)
            ->and($addon->requires)->toBe($provider->dependencies)
            ->and($addon->flag)->toBe($provider->featureFlag);
    }
});
