<?php

/**
 * Unit tests for the setup wizard's pure planning core: it lists the registered kits
 * and turns a chosen kit into an activation plan (modules + feature flags).
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Corex\Kit\BlueprintRegistry;
use Corex\Kit\Company\CompanyBlueprint;
use Corex\Kit\SetupWizard;
use Corex\Woo\WooBlueprint;

function wizardWithKits(): SetupWizard
{
    $registry = new BlueprintRegistry();
    $registry->register(new CompanyBlueprint());
    $registry->register(new WooBlueprint());

    return new SetupWizard($registry);
}

it('lists the registered kits with what each needs', function () {
    $kits = wizardWithKits()->kits();

    $names = array_column($kits, 'name');
    expect($names)->toContain('company', 'woocommerce');

    $woo = array_values(array_filter($kits, fn (array $k): bool => $k['name'] === 'woocommerce'))[0];
    expect($woo['flags'])->toBe(['woocommerce_kit']);
});

it('plans a kit into de-duped modules and its feature flags', function () {
    $plan = wizardWithKits()->plan('woocommerce');

    // required (corex-blocks) + recommended (corex-ui, corex-forms, corex-email), de-duped.
    expect($plan['modules'])->toBe(['corex-blocks', 'corex-ui', 'corex-forms', 'corex-email']);
    expect($plan['flags'])->toBe(['woocommerce_kit']);
});

it('returns an empty plan for an unknown kit', function () {
    expect(wizardWithKits()->plan('nope'))->toBe(['modules' => [], 'flags' => []]);
});

it('plans the company kit with no feature flags', function () {
    expect(wizardWithKits()->plan('company')['flags'])->toBe([]);
});
