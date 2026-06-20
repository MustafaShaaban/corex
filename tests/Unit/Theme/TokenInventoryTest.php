<?php

/**
 * Canonical token authority and inventory contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('keeps every runtime token definition under the canonical theme json authority', function () {
    $inventory = ThemeContract::json('specs/057-brand-tokens-logo-system/inventories/definitions.json');

    expect($inventory['canonical_source'])->toBe('theme/theme.json');

    foreach ($inventory['definitions'] as $definition) {
        expect($definition['source_path'])->toStartWith('theme/theme.json#');
    }
});

it('gives every token one unique identifier and generated property', function () {
    $definitions = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/definitions.json',
    )['definitions'];
    $ids = array_column($definitions, 'id');
    $properties = array_column($definitions, 'generated_property');

    expect(array_values(array_unique($ids)))->toHaveCount(count($definitions))
        ->and(array_values(array_unique($properties)))->toHaveCount(count($definitions));
});

it('maps every generated property record back to exactly one definition', function () {
    $definitions = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/definitions.json',
    )['definitions'];
    $generated = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/generated-properties.json',
    )['properties'];

    $definitionPairs = array_map(
        static fn (array $item): string => $item['id'] . '|' . $item['generated_property'],
        $definitions,
    );
    $generatedPairs = array_map(
        static fn (array $item): string => $item['id'] . '|' . $item['generated_property'],
        $generated,
    );
    sort($definitionPairs);
    sort($generatedPairs);

    expect($generatedPairs)->toBe($definitionPairs);
});
