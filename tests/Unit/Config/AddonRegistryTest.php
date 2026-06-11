<?php

/**
 * Unit tests for the add-on registry (spec 026 US1: FR-001/007, SC-005). Pure — no WordPress.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Config\Addons\Addon;
use Corex\Config\Addons\AddonRegistry;

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
