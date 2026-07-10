<?php

/**
 * Unit tests for the setup wizard's pure planning core: it lists the registered kits
 * and turns a chosen kit into an activation plan (modules + feature flags).
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Kit\BlueprintRegistry;
use Corex\Kit\Company\CompanyBlueprint;
use Corex\Kit\SetupWizard;
use Corex\Woo\WooBlueprint;

beforeEach(function () {
    // Blueprint::pages() composes translatable page content.
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
});

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
    expect(wizardWithKits()->plan('nope'))->toBe(['modules' => [], 'flags' => [], 'pages' => []]);
});

it('plans the company kit with no feature flags', function () {
    expect(wizardWithKits()->plan('company')['flags'])->toBe([]);
});

it('plans fewer pages for the minimal demo level than for full (FR-137)', function () {
    $wizard = wizardWithKits();

    expect(count($wizard->plan('company', 'minimal')['pages']))
        ->toBeLessThan(count($wizard->plan('company', 'full')['pages']));
});

it('exposes the brand-step fields as real brand.* Config keys (FR-135)', function () {
    $fields = wizardWithKits()->brandFields();
    $keys   = array_column($fields, 'key');

    expect($fields)->toHaveCount(8)
        ->and($keys)->toBe([
            'brand.company_name',
            'brand.tagline',
            'brand.phone',
            'brand.email',
            'brand.address',
            'brand.primary_action_label',
            'brand.primary_action_link',
            'brand.social_links',
        ])
        // Every field is a namespaced brand key with a label — never a blank placeholder input.
        ->and(array_filter($fields, static fn (array $f): bool => ! str_starts_with($f['key'], 'brand.') || $f['label'] === ''))->toBe([]);
});
