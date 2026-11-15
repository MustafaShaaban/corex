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

/**
 * The palette and the custom colours, a custom colour keyed `custom.<key>` as
 * `settings.custom.color` writes it (spec 134, FR-002).
 *
 * @return array<string,string> slug => hex colour
 */
function themePalette(string $file): array
{
    $json = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/' . $file), true);
    $colours = array_column($json['settings']['color']['palette'], 'color', 'slug');

    foreach ($json['settings']['custom']['color'] ?? [] as $key => $value) {
        $colours['custom.' . $key] = $value;
    }

    return $colours;
}

/**
 * The colour a block's own `style.color.text` names, as a key of {@see themePalette()}: the first
 * custom property in it the palette defines, so `var(--wp--custom--color--on-accent,
 * var(--wp--preset--color--inverse))` is `custom.onAccent` where the theme has it and `inverse`
 * where it does not.
 *
 * @param array<string,string> $palette
 */
function styleColour(string $value, array $palette): string
{
    preg_match_all('/--wp--(preset|custom)--color--([a-z0-9-]+)/', $value, $names, PREG_SET_ORDER);

    foreach ($names as [, $origin, $name]) {
        $key = $origin === 'custom'
            ? 'custom.' . lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $name))))
            : $name;
        if (isset($palette[$key])) {
            return $key;
        }
    }

    return $value;
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
 * The colours a pattern sets: its section's background and text, its heading's text, its button's
 * background and text. A text colour is a palette slug or the block's own `style.color.text`.
 *
 * @param array<string,string> $palette
 * @return array{section:string,text:string,heading:string,button:string,label:string}
 */
function patternColours(string $content, array $palette): array
{
    $attributes = static function (string $block) use ($content): array {
        preg_match('~<!-- wp:' . $block . ' (\{.*?\}) -->~', $content, $match);

        return (array) json_decode($match[1] ?? '{}', true);
    };
    $text = static function (string $block) use ($attributes, $palette): string {
        $found = $attributes($block);

        return isset($found['textColor'])
            ? (string) $found['textColor']
            : styleColour((string) ($found['style']['color']['text'] ?? ''), $palette);
    };

    return [
        'section' => (string) ($attributes('group')['backgroundColor'] ?? ''),
        'text'    => $text('group'),
        'heading' => $text('heading'),
        'button'  => (string) ($attributes('button')['backgroundColor'] ?? ''),
        'label'   => (string) ($attributes('button')['textColor'] ?? ''),
    ];
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
});

it('reads in the light and the dark palette', function (string $pattern, string $paletteFile) {
    $palette = themePalette($paletteFile);
    $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')[$pattern];
    $slugs   = patternColours($content, $palette);

    foreach ($slugs as $role => $slug) {
        expect(array_key_exists($slug, $palette))->toBeTrue("{$pattern}: its {$role} colour is a palette slug");
    }

    expect(contrastOf($palette[$slugs['text']], $palette[$slugs['section']]))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastOf($palette[$slugs['heading']], $palette[$slugs['section']]))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastOf($palette[$slugs['button']], $palette[$slugs['section']]))->toBeGreaterThanOrEqual(3.0)
        ->and(contrastOf($palette[$slugs['label']], $palette[$slugs['button']]))->toBeGreaterThanOrEqual(4.5);
})->with(['corex/hero', 'corex/cta'])->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// Spec 134 (DECISIONS #635): text on brass is graphite through `onAccent`, and `inverse` where a theme has no such token.
it('draws the call-to-action\'s text in the on-accent colour, falling back to inverse', function () {
    $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')['corex/cta'];
    $withToken = patternColours($content, themePalette('theme.json'));
    $withoutToken = patternColours($content, array_filter(
        themePalette('theme.json'),
        static fn (string $key): bool => ! str_starts_with($key, 'custom.'),
        ARRAY_FILTER_USE_KEY,
    ));

    expect([$withToken['text'], $withToken['heading']])->toBe(['custom.onAccent', 'custom.onAccent'])
        ->and([$withoutToken['text'], $withoutToken['heading']])->toBe(['inverse', 'inverse']);
});
