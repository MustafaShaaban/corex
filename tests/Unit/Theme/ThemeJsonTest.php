<?php

/**
 * Validity tests for the token artifacts: theme.json (the single source) and the
 * dark style variation (US1 FR-001/FR-003/SC-002; US3 FR-008/FR-009).
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

$themeDir = dirname(__DIR__, 3) . '/theme';

/**
 * @return array<string, mixed>
 */
function corex_read_json(string $path): array
{
    expect($path)->toBeFile();
    $decoded = json_decode((string) file_get_contents($path), true);
    expect($decoded)->toBeArray();

    return $decoded;
}

it('ships a valid v3 theme.json that defines every token palette', function () use ($themeDir) {
    $json = corex_read_json($themeDir . '/theme.json');

    expect($json['version'])->toBe(3)
        ->and($json['settings']['color']['palette'])->toBeArray()->not->toBeEmpty()
        ->and($json['settings']['typography']['fontSizes'])->toBeArray()->not->toBeEmpty()
        ->and($json['settings']['spacing']['spacingSizes'])->toBeArray()->not->toBeEmpty()
        ->and($json['settings']['layout'])->toBeArray()->not->toBeEmpty();
});

it('exposes the theme styling through preset variables only (no hardcoded values)', function () use ($themeDir) {
    $styles = corex_read_json($themeDir . '/theme.json')['styles'];

    // Every leaf in styles that references a value uses a --wp--preset--* custom property.
    array_walk_recursive($styles, function ($leaf): void {
        if (is_string($leaf)) {
            expect($leaf)->toContain('var(--wp--preset--');
        }
    });
});

it('ships a valid v3 dark style variation that overrides tokens only', function () use ($themeDir) {
    $json = corex_read_json($themeDir . '/styles/dark.json');

    expect($json['version'])->toBe(3)
        ->and($json['settings']['color']['palette'])->toBeArray()->not->toBeEmpty();

    // A variation is a skin: it carries no template/part/CPT wiring, only settings + styles.
    expect($json)->not->toHaveKey('templateParts')
        ->and($json)->not->toHaveKey('customTemplates');

    array_walk_recursive($json['styles'], function ($leaf): void {
        if (is_string($leaf)) {
            expect($leaf)->toContain('var(--wp--preset--');
        }
    });
});
