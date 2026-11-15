<?php

/**
 * Brass is a ground, a ring or an edge, never the colour of text on a page (spec 134, US-BRD-01,
 * FR-007; the hand-over's "Accessibility expectations").
 *
 * The light brass is 3.35:1 on white: enough for a focus ring or a button's edge, not for words or
 * for a mark that carries meaning. Text in brass is drawn in `accent-dark`. A stylesheet that gives
 * text the `accent` colour fails here, naming the file and the line.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

it('draws no text in brass in any CoreX stylesheet', function () {
    $found = [];

    foreach (ThemeContract::sourceFiles() as $path) {
        if (! preg_match('/\.(?:css|scss)$/', $path)) {
            continue;
        }
        $lines = explode("\n", (string) file_get_contents(ThemeContract::root() . '/' . $path));
        foreach ($lines as $number => $line) {
            if (preg_match('/(?<![\w-])color\s*:\s*var\(\s*--wp--preset--color--accent\s*[,)]/', $line) === 1) {
                $found[] = sprintf('%s:%d', $path, $number + 1);
            }
        }
    }

    expect($found)->toBe([]);
});

it('draws the form\'s required mark in the darker brass', function () {
    $css = (string) file_get_contents(ThemeContract::root() . '/plugins/corex-forms/src/Block/blocks/corex-form/style.scss');

    expect($css)->toMatch('/\.corex-form__required\s*\{\s*color:\s*var\(--wp--preset--color--accent-dark\);/');
});

// Spec 134 (DECISIONS #635): white on the light brass is 3.35:1, so text on a brass ground is `onAccent`.
it('draws the text of every rule with a brass ground in the on-accent colour', function () {
    $found = [];

    foreach (ThemeContract::sourceFiles() as $path) {
        if (! preg_match('/\.(?:css|scss)$/', $path)) {
            continue;
        }
        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(ThemeContract::root() . '/' . $path));
        preg_match_all('/([^{};]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER);

        foreach ($rules as [, $selector, $body]) {
            if (preg_match('/(?<![\w-])background(?:-color)?\s*:\s*var\(\s*--wp--preset--color--accent\s*[,)]/', $body) !== 1) {
                continue;
            }
            if (preg_match('/(?<![\w-])color\s*:\s*([^;]+)/', $body, $colour) === 1
                && ! str_starts_with(trim($colour[1]), 'var(--wp--custom--color--on-accent,')
            ) {
                $found[] = sprintf('%s: %s draws %s on brass', $path, trim($selector), trim($colour[1]));
            }
        }
    }

    expect($found)->toBe([]);
});

it('draws the newsletter button\'s label and the mega menu badge in the on-accent colour', function () {
    $onAccent = 'color: var(--wp--custom--color--on-accent, var(--wp--preset--color--inverse));';

    expect((string) file_get_contents(ThemeContract::root() . '/plugins/corex-blocks/src/blocks/newsletter-signup/style.scss'))->toContain($onAccent)
        ->and((string) file_get_contents(ThemeContract::root() . '/theme/assets/css/corex-navigation.css'))->toContain($onAccent);
});

// FR-007: a theme pattern gives small text the darker brass; brass passes only as large text (3:1).
it('draws no small text in brass in a theme pattern', function () {
    $large = ['xl', '2xl', '3xl', 'hero', 'display'];
    $found = [];

    foreach ((array) glob(ThemeContract::root() . '/theme/patterns/*.php') as $file) {
        preg_match_all('/<!-- wp:[a-z\/-]+ (\{.*?\}) \/?-->/', (string) file_get_contents((string) $file), $blocks);

        foreach ($blocks[1] as $json) {
            $attributes = (array) json_decode($json, true);
            if (($attributes['textColor'] ?? '') === 'accent' && ! in_array($attributes['fontSize'] ?? '', $large, true)) {
                $found[] = basename((string) $file) . ': ' . $json;
            }
        }
    }

    expect($found)->toBe([]);
});
