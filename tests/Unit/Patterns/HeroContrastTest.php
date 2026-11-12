<?php

/**
 * The hero and call-to-action patterns read in both of the CoreX theme's palettes (spec 115, US-SAFE-15,
 * FR-018 and FR-020): the heading against its section at least 4.5:1, each button against its section
 * at least 3:1, and a button's label against the button at least 4.5:1. Measured from the palette
 * slugs the patterns name and the colours `theme.json` and its dark variation give them, so a change to
 * either is checked here; the browser measures the light palette as drawn (`front-page-hero.spec.js`).
 *
 * @package Corex\Tests\Unit\Patterns
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Patterns\PatternLibrary;

/** @return array<string,string> slug => hex colour */
function themePalette(string $file): array
{
    $json = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/' . $file), true);

    return array_column($json['settings']['color']['palette'], 'color', 'slug');
}

function relativeLuminance(string $hex): float
{
    $channels = array_map(
        static fn (string $pair): float => hexdec($pair) / 255,
        str_split(ltrim($hex, '#'), 2),
    );
    [$r, $g, $b] = array_map(
        static fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
        $channels,
    );

    return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
}

function contrastOf(string $a, string $b): float
{
    [$light, $dark] = [max(relativeLuminance($a), relativeLuminance($b)), min(relativeLuminance($a), relativeLuminance($b))];

    return ($light + 0.05) / ($dark + 0.05);
}

/**
 * The colour slugs a pattern sets: its section's background, its heading's text, its button's
 * background and text.
 *
 * @return array{section:string,heading:string,button:string,label:string}
 */
function patternColours(string $content): array
{
    $attribute = static function (string $block, string $key) use ($content): string {
        preg_match('~<!-- wp:' . $block . ' (\{.*?\}) -->~', $content, $match);
        $attributes = json_decode($match[1] ?? '{}', true);

        return (string) ($attributes[$key] ?? '');
    };

    return [
        'section' => $attribute('group', 'backgroundColor'),
        'heading' => $attribute('heading', 'textColor'),
        'button'  => $attribute('button', 'backgroundColor'),
        'label'   => $attribute('button', 'textColor'),
    ];
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
});

it('reads in the light and the dark palette', function (string $pattern, string $paletteFile) {
    $palette = themePalette($paletteFile);
    $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')[$pattern];
    $slugs   = patternColours($content);

    foreach ($slugs as $role => $slug) {
        expect(array_key_exists($slug, $palette))->toBeTrue("{$pattern}: its {$role} colour is a palette slug");
    }

    expect(contrastOf($palette[$slugs['heading']], $palette[$slugs['section']]))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastOf($palette[$slugs['button']], $palette[$slugs['section']]))->toBeGreaterThanOrEqual(3.0)
        ->and(contrastOf($palette[$slugs['label']], $palette[$slugs['button']]))->toBeGreaterThanOrEqual(4.5);
})->with(['corex/hero', 'corex/cta'])->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);
