<?php

/**
 * Spec 060 / M6 US1 — the Add-ons screen view resolves to one truthful AddonStatus.
 *
 * Bridges the existing per-add-on AddonView to the canonical seven-state AddonStatus so
 * the Add-ons screen renders one honest state and offers enable/disable only for
 * installed add-ons (never not_installed / pro_required).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Config\Addons\Addon;
use Corex\Config\Addons\AddonView;
use Corex\Foundation\AddonStatus;

function addon(string $slug = 'corex-captcha', ?string $flag = null): Addon
{
    return new Addon(slug: $slug, pluginFile: $slug . '/' . $slug . '.php', label: $slug, flag: $flag);
}

function view(
    bool $installed,
    bool $active,
    bool $flagOn = false,
    ?string $flag = null,
    bool $dependencyMissing = false,
    bool $wooMissing = false,
    bool $proRequired = false,
): AddonView {
    return new AddonView(
        addon: addon('corex-captcha', $flag),
        installed: $installed,
        active: $active,
        flagOn: $flagOn,
        blockedReason: null,
        dependencyMissing: $dependencyMissing,
        wooMissing: $wooMissing,
        proRequired: $proRequired,
    );
}

it('maps pro_required first and forbids toggling', function () {
    $status = view(installed: false, active: false, proRequired: true)->status();

    expect($status)->toBe(AddonStatus::ProRequired)
        ->and($status->canToggle())->toBeFalse();
});

it('maps a not-installed add-on to not_installed (no toggle)', function () {
    $status = view(installed: false, active: false)->status();

    expect($status)->toBe(AddonStatus::NotInstalled)
        ->and($status->canToggle())->toBeFalse();
});

it('maps installed-but-inactive to inactive (togglable)', function () {
    $status = view(installed: true, active: false)->status();

    expect($status)->toBe(AddonStatus::Inactive)
        ->and($status->canToggle())->toBeTrue();
});

it('maps an active add-on with a missing dependency to dependency_missing', function () {
    expect(view(installed: true, active: true, dependencyMissing: true)->status())
        ->toBe(AddonStatus::DependencyMissing);
});

it('maps an active add-on whose feature flag is off to feature_off', function () {
    expect(view(installed: true, active: true, flagOn: false, flag: 'captcha')->status())
        ->toBe(AddonStatus::FeatureOff);
});

it('maps a closed WooCommerce gate to woocommerce_missing', function () {
    expect(view(installed: true, active: true, wooMissing: true)->status())
        ->toBe(AddonStatus::WoocommerceMissing);
});

it('maps a fully-satisfied add-on to active', function () {
    expect(view(installed: true, active: true, flagOn: true, flag: 'captcha')->status())
        ->toBe(AddonStatus::Active);
});

it('treats an active add-on with no feature flag as active', function () {
    expect(view(installed: true, active: true)->status())->toBe(AddonStatus::Active);
});
