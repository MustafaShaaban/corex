<?php

/**
 * Spec 060 / M6 — the truthful add-on display-state resolver.
 *
 * Pure, headless: every runtime combination resolves to exactly one AddonStatus,
 * in the order pro_required → not_installed → inactive → dependency_missing →
 * feature_off → woocommerce_missing → active. Toggling is allowed only for installed
 * add-ons (never not_installed / pro_required).
 *
 * @package Corex\Tests\Unit\Foundation
 */

declare(strict_types=1);

use Corex\Foundation\AddonProvider;
use Corex\Foundation\AddonRuntimeState;
use Corex\Foundation\AddonStatus;
use Corex\Foundation\AddonStatusResolver;

function addonProvider(
    string $slug = 'corex-captcha',
    array $dependencies = [],
    ?string $featureFlag = null,
    ?string $externalGate = null,
): AddonProvider {
    return new AddonProvider(
        slug: $slug,
        providerClass: \Corex\Foundation\ServiceProvider::class,
        pluginFile: $slug . '/' . $slug . '.php',
        dependencies: $dependencies,
        featureFlag: $featureFlag,
        externalGate: $externalGate,
    );
}

function runtime(
    array $active = [],
    array $installed = [],
    array $flags = [],
    array $gates = [],
): AddonRuntimeState {
    return new AddonRuntimeState($active, $installed, $flags, $gates);
}

it('reports not_installed when the package is absent', function () {
    $p = addonProvider();
    $status = (new AddonStatusResolver())->resolve($p, runtime());

    expect($status)->toBe(AddonStatus::NotInstalled)
        ->and($status->canToggle())->toBeFalse()
        ->and($status->isUsable())->toBeFalse();
});

it('reports inactive when installed but the plugin is not active', function () {
    $p = addonProvider();
    $status = (new AddonStatusResolver())->resolve($p, runtime(installed: [$p->pluginFile]));

    expect($status)->toBe(AddonStatus::Inactive)
        ->and($status->canToggle())->toBeTrue()
        ->and($status->isUsable())->toBeFalse();
});

it('reports dependency_missing when an active add-on has an unmet dependency', function () {
    $p = addonProvider(dependencies: ['corex-ui']);
    $status = (new AddonStatusResolver())->resolve(
        $p,
        runtime(active: [$p->slug], installed: [$p->pluginFile]),
        satisfiedSlugs: [],
    );

    expect($status)->toBe(AddonStatus::DependencyMissing);
});

it('reports feature_off when active and dependencies met but the flag is off', function () {
    $p = addonProvider(featureFlag: 'captcha');
    $status = (new AddonStatusResolver())->resolve(
        $p,
        runtime(active: [$p->slug], installed: [$p->pluginFile]),
    );

    expect($status)->toBe(AddonStatus::FeatureOff);
});

it('reports woocommerce_missing when the WooCommerce gate is closed', function () {
    $p = addonProvider(slug: 'corex-kit-woo', externalGate: 'woocommerce');
    $status = (new AddonStatusResolver())->resolve(
        $p,
        runtime(active: [$p->slug], installed: [$p->pluginFile], gates: ['woocommerce' => false]),
    );

    expect($status)->toBe(AddonStatus::WoocommerceMissing);
});

it('reports active when installed, active, dependencies met, flag on, gate open', function () {
    $p = addonProvider(featureFlag: 'captcha', externalGate: 'woocommerce');
    $status = (new AddonStatusResolver())->resolve(
        $p,
        runtime(
            active: [$p->slug],
            installed: [$p->pluginFile],
            flags: ['captcha'],
            gates: ['woocommerce' => true],
        ),
    );

    expect($status)->toBe(AddonStatus::Active)
        ->and($status->isUsable())->toBeTrue()
        ->and($status->canToggle())->toBeTrue();
});

it('reports pro_required first — even when not installed — and forbids toggling', function () {
    $p = addonProvider(slug: 'corex-pro-thing');
    $status = (new AddonStatusResolver())->resolve($p, runtime(), proRequired: true);

    expect($status)->toBe(AddonStatus::ProRequired)
        ->and($status->canToggle())->toBeFalse()
        ->and($status->isUsable())->toBeFalse();
});

it('treats an active add-on with a satisfied dependency as active', function () {
    $p = addonProvider(dependencies: ['corex-ui']);
    $status = (new AddonStatusResolver())->resolve(
        $p,
        runtime(active: [$p->slug], installed: [$p->pluginFile]),
        satisfiedSlugs: ['corex-ui'],
    );

    expect($status)->toBe(AddonStatus::Active);
});
