<?php

/**
 * Retention, alias, deprecation, and rollback contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('retains every inventoried stable token slug during migration', function () {
    $definitions = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/definitions.json',
    )['definitions'];
    $retained = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/classifications.json',
    )['retained'];
    $retainedDefinitions = array_values(array_filter(
        $definitions,
        static fn (array $definition): bool => $definition['classification'] === 'retained',
    ));

    expect(array_column($retained, 'id'))->toEqualCanonicalizing(array_column($retainedDefinitions, 'id'));
});

it('keeps every planned legacy alias functional for the compatibility window', function () {
    $definitions = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/definitions.json',
    )['definitions'];
    $definedProperties = array_column($definitions, 'generated_property');
    $aliases = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/classifications.json',
    )['aliased'];
    $missing = array_values(array_filter(
        $aliases,
        static fn (array $alias): bool => ! in_array($alias['legacy_property'], $definedProperties, true),
    ));

    expect($missing)->toBe([]);
});

it('records concrete introduction and removal eligibility for active aliases', function () {
    $aliases = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/classifications.json',
    )['aliased'];
    $invalid = array_values(array_filter(
        $aliases,
        static fn (array $alias): bool => ! preg_match('/^\d+\.\d+\.\d+$/', $alias['introduced_version'])
            || ! preg_match('/^\d+\.\d+\.\d+$/', $alias['remove_after_version']),
    ));

    expect($invalid)->toBe([]);
});

it('does not mark aliases removal eligible while first party consumers remain', function () {
    $aliases = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/classifications.json',
    )['aliased'];
    $unsafe = array_values(array_filter(
        $aliases,
        static fn (array $alias): bool => $alias['consumer_paths'] !== []
            && ($alias['status'] ?? '') === 'removal-eligible',
    ));

    expect($unsafe)->toBe([]);
});
