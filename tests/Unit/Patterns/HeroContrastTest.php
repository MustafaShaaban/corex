<?php

/**
 * The hero and call-to-action patterns read in both of the CoreX theme's palettes (spec 115, US-SAFE-15,
 * FR-018 and FR-020; spec 134, FR-016): the heading against its section at least 4.5:1, each button
 * against its section at least 3:1, and a button's label against the button at least 4.5:1.
 *
 * Since spec 134 the patterns name no colours: the hero is the dark band and the call to action the
 * brass band, section styles of corex-ui that re-point the palette for what is inside them
 * (DECISIONS #640). So the colours are worked out from the band's stylesheet with the colours
 * `theme.json` and its dark variation give the tokens ({@see SectionStyleCascade}). Under a theme with
 * the CoreX roles and no band tokens they must be the colours each pattern named before (FR-015); under
 * a client theme with a palette of its own there is no band (DECISIONS #642) and the patterns name no
 * colour, so the theme draws them as it drew the old ones, whose colour classes it had no rule for.
 * The browser measures the same as drawn (`front-page-hero.spec.js`, `section-bands.spec.js`).
 *
 * @package Corex\Tests\Unit\Patterns
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Unit\Patterns\SectionStyleCascade;
use Corex\Ui\Blocks\BlockStyles;
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
 * A theme built on the CoreX roles with none of the band tokens: no custom colours, and a value of
 * its own for every role, so a colour drawn from the wrong role shows.
 *
 * @return array<string,string>
 */
function rolesPalette(): array
{
    return [
        'primary' => '#1f3a5f', 'primary-dark' => '#132840', 'accent' => '#c8553d', 'accent-dark' => '#7a2e1f',
        'surface' => '#fbfaf7', 'surface-alt' => '#eeebe4', 'surface-raised' => '#fdfcfa', 'surface-strong' => '#dcd7cc',
        'border' => '#857d70', 'ink' => '#221f1b', 'ink-soft' => '#5a544b', 'inverse' => '#fffefc',
        'success' => '#2a6a3f', 'warning' => '#7d5410', 'error' => '#a8322b', 'info' => '#1d5f86',
        'selection' => '#c8553d', 'selection-text' => '#221f1b',
    ];
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

/** The section style a pattern's outer group takes (`corex-band-dark`), or '' with none. */
function patternSectionStyle(string $content): string
{
    preg_match('~<!-- wp:group (\{.*?\}) -->~', $content, $match);
    $className = (string) (((array) json_decode($match[1] ?? '{}', true))['className'] ?? '');
    preg_match('/\bis-style-(corex-band-[a-z]+)\b/', $className, $style);

    return $style[1] ?? '';
}

/**
 * What a pattern draws on a page with the palette, through its section style.
 *
 * @param array<string,string> $palette
 * @return array<string,?string>
 */
function patternDrawn(string $pattern, array $palette): array
{
    $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')[$pattern];
    $style = patternSectionStyle($content);
    expect($style)->not->toBe('', "{$pattern} takes a section style");

    return SectionStyleCascade::fromStylesheet()->draw($style, $palette);
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
});

it('reads in the light and the dark palette', function (string $pattern, string $paletteFile) {
    $drawn = patternDrawn($pattern, themePalette($paletteFile));

    foreach (['section', 'text', 'heading', 'button', 'label', 'buttonHover', 'ring'] as $role) {
        expect((string) $drawn[$role])->toMatch('/^#[0-9a-f]{6}$/i', "{$pattern}: its {$role} colour resolves to a theme colour");
    }

    expect(contrastOf($drawn['text'], $drawn['section']))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastOf($drawn['heading'], $drawn['section']))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastOf($drawn['button'], $drawn['section']))->toBeGreaterThanOrEqual(3.0)
        ->and(contrastOf($drawn['label'], $drawn['button']))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastOf($drawn['buttonHover'], $drawn['section']))->toBeGreaterThanOrEqual(3.0)
        ->and(contrastOf($drawn['label'], $drawn['buttonHover']))->toBeGreaterThanOrEqual(4.5)
        ->and(contrastOf($drawn['ring'], $drawn['section']))->toBeGreaterThanOrEqual(3.0);
})->with(['corex/hero', 'corex/cta'])->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// SC-005: the hero is a dark band in both modes, and its button is brass with the band's graphite label.
it('draws the hero dark in both modes, with a brass button and a graphite label', function (string $paletteFile) {
    $palette = themePalette($paletteFile);
    $drawn = patternDrawn('corex/hero', $palette);

    expect([$drawn['section'], $drawn['text'], $drawn['heading']])
        ->toBe([$palette['custom.band'], $palette['custom.bandInk'], $palette['custom.bandInk']])
        ->and([$drawn['button'], $drawn['label']])->toBe([$palette['custom.bandAccent'], $palette['custom.band']])
        ->and(relativeLuminance((string) $drawn['section']))->toBeLessThan(0.02);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// FR-013, DECISIONS #642: the brass band's text and heading are graphite and its button the kit's graphite.
it('draws the call to action on brass with graphite text and a graphite button', function (string $paletteFile) {
    $palette = themePalette($paletteFile);
    $drawn = patternDrawn('corex/cta', $palette);

    expect([$drawn['section'], $drawn['text'], $drawn['heading']])
        ->toBe([$palette['accent'], $palette['custom.onAccent'], $palette['custom.onAccent']])
        ->and([$drawn['button'], $drawn['label']])->toBe([$palette['custom.onAccent'], $palette['custom.bandInk']])
        ->and(relativeLuminance((string) $drawn['button']))->toBeLessThan(0.02);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// FR-015, constitution IX: under a theme with the CoreX roles and no band tokens each pattern draws its old colours.
it('draws each pattern as it did before under a theme with the CoreX roles and no band tokens', function (string $pattern, array $before) {
    $palette = rolesPalette();
    expect((new BlockStyles('', ''))->bandsAvailable(array_keys($palette), []))->toBeTrue();

    expect(patternDrawn($pattern, $palette))->toMatchArray(array_map(
        static fn (string $slug): string => $palette[$slug],
        $before,
    ));
})->with([
    'hero' => ['corex/hero', [
        'section' => 'primary', 'text' => 'surface', 'heading' => 'surface',
        'button' => 'surface', 'label' => 'primary', 'buttonHover' => 'surface', 'ring' => 'surface',
    ]],
    'call to action' => ['corex/cta', [
        'section' => 'accent', 'text' => 'inverse', 'heading' => 'inverse',
        'button' => 'surface', 'label' => 'accent-dark', 'buttonHover' => 'surface', 'ring' => 'surface',
    ]],
]);

// Spec 134 (DECISIONS #635): text on brass is graphite through `onAccent`, and `inverse` where a theme has no such token.
it('draws the call-to-action\'s text in the on-accent colour, falling back to inverse', function () {
    $light = themePalette('theme.json');
    $withToken = patternDrawn('corex/cta', $light);
    $withoutToken = patternDrawn('corex/cta', array_filter(
        $light,
        static fn (string $key): bool => ! str_starts_with($key, 'custom.'),
        ARRAY_FILTER_USE_KEY,
    ));

    expect([$withToken['text'], $withToken['heading']])->toBe([$light['custom.onAccent'], $light['custom.onAccent']])
        ->and([$withoutToken['text'], $withoutToken['heading']])->toBe([$light['inverse'], $light['inverse']]);
});

/*
 * Perego's and Muva's own palettes (their theme.json, read-only): no CoreX role. Neither styles the
 * button, so WordPress draws it in its default; the old patterns' colour classes named slugs these
 * themes lack, and WordPress prints no rule for those.
 */
it('leaves the hero and call to action to a client theme with a palette of its own', function (array $slugs) {
    expect((new BlockStyles('', ''))->bandsAvailable($slugs, []))->toBeFalse();

    foreach (['corex/hero', 'corex/cta'] as $pattern) {
        $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')[$pattern];
        preg_match_all('/class="([^"]*)"/', $content, $classes);
        $named = preg_grep('/^has-.*(?:color|background)$/', explode(' ', implode(' ', $classes[1])));

        expect($named)->toBe([], "{$pattern} names a colour class")
            ->and($content)->not->toContain('--wp--preset--color--')
            ->and($content)->not->toContain('--wp--custom--color--');
    }
})->with([
    'perego-like' => [['bg-deep', 'violet', 'accent', 'accent-soft', 'panel', 'text']],
    'muva-like' => [['night', 'black', 'white', 'green', 'error', 'panel', 'text', 'stat', 'card', 'ink']],
]);
