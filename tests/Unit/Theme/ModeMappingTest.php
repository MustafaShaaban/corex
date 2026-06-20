<?php

/**
 * Complete semantic and style-variation mapping contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('covers every required semantic role group', function () {
    $classifications = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/classifications.json',
    );
    $ids = array_merge(
        array_column($classifications['retained'], 'id'),
        array_column($classifications['added'], 'id'),
    );
    $requiredRoles = [
        'surface', 'text', 'border', 'accent', 'status', 'overlay', 'selection', 'focus',
        'radius', 'spacing', 'shadow', 'motion', 'z',
    ];
    $missing = array_values(array_filter(
        $requiredRoles,
        static fn (string $role): bool => ! array_filter(
            $ids,
            static fn (string $id): bool => str_contains($id, $role),
        ),
    ));

    expect($missing)->toBe([]);
});

it('provides complete default and dark mappings for client-facing colors and fonts', function () {
    $definitions = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/definitions.json',
    )['definitions'];
    $incomplete = array_values(array_filter(
        $definitions,
        static fn (array $definition): bool => in_array($definition['group'], ['color', 'font-family'], true)
            && ($definition['default_mapping'] === null || $definition['dark_mapping'] === null),
    ));

    expect($incomplete)->toBe([]);
});

it('ships complete palette and font replacement arrays for every style variation', function () {
    $variations = ThemeContract::json(
        'specs/057-brand-tokens-logo-system/inventories/variations.json',
    )['variations'];
    $incomplete = [];

    foreach ($variations as $variation) {
        foreach (['palette', 'font_families'] as $list) {
            if (! $variation['replacement_arrays'][$list]['complete']) {
                $incomplete[] = $variation['mode'] . ':' . $list;
            }
        }
    }

    expect($incomplete)->toBe([]);
});
