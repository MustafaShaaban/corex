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

/**
 * The admin's own tokens (spec 116, US-NFR-01): the pairs above are the front-end theme's, and
 * the admin paints every CoreX screen from a different set. A token change that breaks a pair
 * fails here, before any browser opens. A known failure is listed with the story that fixes it;
 * one that passes again fails too, so the list only shrinks.
 */
it('meets every admin token contrast and focus pairing, dark and light', function () {
    $matrix = ThemeContract::json('tests/Fixtures/Theme/admin-contrast-matrix.json');
    $palettes = ThemeContract::adminPalettes();
    $known = array_column($matrix['knownFailures'], null, 'id');
    $violations = [];
    $stale = [];

    foreach ($matrix['pairs'] as $pair) {
        $palette = $palettes[$pair['mode']];
        $foreground = $palette[$pair['foreground']] ?? null;
        $background = $palette[$pair['background']] ?? null;

        if ($foreground === null || $background === null) {
            $violations[] = sprintf('%s: a token is missing from the %s block', $pair['id'], $pair['mode']);
            continue;
        }

        $ratio = ThemeContract::contrastRatio($foreground, $background);
        $fails = $ratio < (float) $pair['threshold'];

        if ($fails && ! isset($known[$pair['id']])) {
            $violations[] = sprintf(
                '%s: %s on %s is %.2f:1, below %.1f:1',
                $pair['id'],
                $foreground,
                $background,
                $ratio,
                $pair['threshold'],
            );
        }

        if (! $fails && isset($known[$pair['id']])) {
            $stale[] = sprintf('%s now passes (%.2f:1): remove it from knownFailures', $pair['id'], $ratio);
        }
    }

    expect($violations)->toBe([])
        ->and($stale)->toBe([]);
});

it('lists each known admin contrast failure with its date and story', function () {
    $matrix = ThemeContract::json('tests/Fixtures/Theme/admin-contrast-matrix.json');
    $ids = array_column($matrix['pairs'], 'id');

    foreach ($matrix['knownFailures'] as $entry) {
        expect($entry)->toHaveKeys(['id', 'since', 'story', 'note'])
            ->and($ids)->toContain($entry['id'])
            ->and($entry['since'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
            ->and($entry['story'])->toMatch('/^US-[A-Z]+-\d+$/');
    }
});

it('gives the system appearance and a pinned light appearance the same light colours', function () {
    $palettes = ThemeContract::adminPalettes();
    $differ = [];

    foreach ($palettes['light'] as $token => $value) {
        if (($palettes['light-pinned'][$token] ?? null) !== $value) {
            $differ[] = sprintf('%s: %s by the system, %s pinned', $token, $value, $palettes['light-pinned'][$token] ?? 'missing');
        }
    }

    expect($palettes['light'])->not->toBe([])
        ->and($differ)->toBe([]);
});
