<?php

/**
 * WCAG contrast and focus-pair evidence contracts for Spec 057.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('defines unique light and dark evidence pairs with the required thresholds', function () {
    $pairs = ThemeContract::json(
        'tests/Fixtures/Theme/contrast-focus-matrix.json',
    )['pairs'];
    $ids = array_column($pairs, 'id');

    expect(array_values(array_unique($ids)))->toHaveCount(count($pairs));

    foreach ($pairs as $pair) {
        expect($pair)->toHaveKeys(['id', 'mode', 'foreground', 'background', 'kind', 'threshold'])
            ->and($pair['mode'])->toBeIn(['light', 'dark'])
            ->and($pair['kind'])->toBeIn(['normal-text', 'large-text', 'non-text', 'focus'])
            ->and((float) $pair['threshold'])->toBe(
                in_array($pair['kind'], ['normal-text'], true) ? 4.5 : 3.0,
            );
    }
});

it('meets every light and dark contrast and focus pairing', function () {
    $light = ThemeContract::palette(ThemeContract::json('theme/theme.json'));
    $dark = ThemeContract::palette(ThemeContract::json('theme/styles/dark.json'));
    $pairs = ThemeContract::json(
        'tests/Fixtures/Theme/contrast-focus-matrix.json',
    )['pairs'];
    $violations = [];

    foreach ($pairs as $pair) {
        $palette = $pair['mode'] === 'dark' ? $dark : $light;
        $foreground = $palette[$pair['foreground']] ?? null;
        $background = $palette[$pair['background']] ?? null;

        if ($foreground === null || $background === null) {
            $violations[] = sprintf(
                '%s missing %s',
                $pair['id'],
                implode(', ', array_filter([
                    $foreground === null ? $pair['foreground'] : null,
                    $background === null ? $pair['background'] : null,
                ])),
            );
            continue;
        }

        $ratio = ThemeContract::contrastRatio($foreground, $background);

        if ($ratio < (float) $pair['threshold']) {
            $violations[] = sprintf(
                '%s %.2f:1 < %.1f:1',
                $pair['id'],
                $ratio,
                $pair['threshold'],
            );
        }
    }

    expect($violations)->toBe([]);
});
