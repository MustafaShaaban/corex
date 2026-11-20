<?php

/**
 * The section bands' rules (spec 134, FR-011, FR-013, FR-014; DECISIONS #640, #642), read from
 * corex-ui's `section-bands.css` and `block-styles.css` with the theme's own CSS. The rules live in
 * corex-ui, not the theme, because the hero and call-to-action patterns that use them are corex-ui's.
 *
 * Worked out with {@see SectionStyleCascade} in the CoreX theme's light and dark palettes and in a
 * theme that has the CoreX roles and none of the band tokens. A client theme with a palette of its
 * own (Perego's, Muva's) gets no band at all (`BlockStylesTest`), so the band class is inert there.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Corex\Tests\Unit\Patterns\SectionStyleCascade;
use Corex\Ui\Blocks\BlockStyles;

/** @return array<string,string> slug or `custom.<key>` => colour */
function sectionStylesPalette(string $file): array
{
    $json = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/' . $file), true);
    $colours = array_column($json['settings']['color']['palette'], 'color', 'slug');

    foreach ($json['settings']['custom']['color'] ?? [] as $key => $value) {
        $colours['custom.' . $key] = $value;
    }

    return $colours;
}

/** The thirteen roles `BrandOverrideValidator` asks of a brand palette, which replaces the theme's whole. */
const SECTION_STYLES_BRAND_ROLES = ['primary', 'primary-dark', 'accent', 'accent-dark', 'surface', 'surface-alt', 'border', 'ink', 'ink-soft', 'success', 'warning', 'error', 'info'];

/**
 * A palette by name: `theme.json`, `styles/dark.json`, `roles` (the CoreX roles, no band tokens) or
 * `brand` (a brand palette of the thirteen roles on the CoreX theme, which keeps its custom colours).
 *
 * @return array<string,string>
 */
function sectionStylesNamed(string $name): array
{
    if ($name === 'brand') {
        return array_filter(
            sectionStylesPalette('theme.json'),
            static fn (string $key): bool => str_starts_with($key, 'custom.') || in_array($key, SECTION_STYLES_BRAND_ROLES, true),
            ARRAY_FILTER_USE_KEY,
        );
    }
    if ($name !== 'roles') {
        return sectionStylesPalette($name);
    }

    // A theme with the CoreX roles and no band tokens: the CoreX palette without its custom colours.
    return array_filter(
        sectionStylesPalette('theme.json'),
        static fn (string $key): bool => ! str_starts_with($key, 'custom.'),
        ARRAY_FILTER_USE_KEY,
    );
}

/** The WCAG contrast of two `#rrggbb` colours. */
function sectionStylesContrast(string $a, string $b): float
{
    $luminance = static function (string $hex): float {
        $channels = array_map(static function (string $pair): float {
            $c = hexdec($pair) / 255;

            return $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
        }, str_split(ltrim($hex, '#'), 2));

        return 0.2126 * $channels[0] + 0.7152 * $channels[1] + 0.0722 * $channels[2];
    };
    [$x, $y] = [$luminance($a), $luminance($b)];

    return (max($x, $y) + 0.05) / (min($x, $y) + 0.05);
}

function sectionStylesCss(string $file): string
{
    return (string) preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(dirname(__DIR__, 3) . '/addons/corex-ui/assets/' . $file));
}

/**
 * The palette properties the dark band re-points for what is inside it, and the token each takes:
 * the kit's `.corex-section--band`, with the compatibility slugs following their roles.
 *
 * @return array<string,string> property => palette key it takes in the CoreX theme
 */
function darkBandRepointing(): array
{
    return [
        '--wp--preset--color--surface'          => 'custom.band',
        '--wp--preset--color--background'       => 'custom.band',
        '--wp--preset--color--surface-alt'      => 'custom.bandRaised',
        '--wp--preset--color--surface-raised'   => 'custom.bandRaised',
        '--wp--preset--color--surface-strong'   => 'custom.bandLine',
        '--wp--preset--color--border'           => 'custom.bandBorder',
        '--wp--preset--color--ink'              => 'custom.bandInk',
        '--wp--preset--color--foreground'       => 'custom.bandInk',
        '--wp--preset--color--ink-soft'         => 'custom.bandInkSoft',
        '--wp--preset--color--primary'          => 'custom.bandInk',
        '--wp--preset--color--corex-primary'    => 'custom.bandInk',
        '--wp--preset--color--primary-dark'     => 'custom.bandInkSoft',
        '--wp--preset--color--inverse'          => 'custom.band',
        '--wp--preset--color--white'            => 'custom.band',
        '--wp--preset--color--accent'           => 'custom.bandAccent',
        '--wp--preset--color--accent-dark'      => 'custom.bandLink',
        '--wp--preset--color--selection'        => 'custom.bandAccent',
        '--wp--custom--color--on-accent'        => 'custom.band',
        '--wp--custom--color--accent-hover'     => 'custom.bandAccentHover',
        '--wp--custom--color--accent-subtle'    => 'custom.bandAccentSubtle',
        '--wp--custom--focus--color'            => 'custom.bandAccent',
    ];
}

/**
 * What a child of the band holds for each property, in a palette.
 *
 * @param array<string,string> $palette
 * @return array<string,?string>
 */
function bandChild(string $style, array $palette): array
{
    $cascade = SectionStyleCascade::fromStylesheet();
    $section = SectionStyleCascade::element(SectionStyleCascade::page($palette), $cascade->rule('.wp-block-group.is-style-' . $style));

    return SectionStyleCascade::element($section, $cascade->rule('.wp-block-group.is-style-' . $style . ' > *'));
}

/** @return list<string> the selectors of the band's default-button rules */
function bandButtonSelectors(): array
{
    preg_match_all('~([^{}]+)\{[^{}]*var\(--corex-band-button~', sectionStylesCss('section-bands.css'), $rules);

    return array_map(static fn (string $selector): string => (string) preg_replace('/\s+/', ' ', trim($selector)), $rules[1]);
}

it('re-points every palette token to its band token for what is inside the dark band, as the kit does (FR-013)', function (string $file) {
    $palette = sectionStylesPalette($file);
    $child = bandChild('corex-band-dark', $palette);
    $wrong = [];

    foreach (darkBandRepointing() as $property => $token) {
        if (($child[$property] ?? null) !== $palette[$token]) {
            $wrong[] = sprintf('%s is %s, not %s (%s)', $property, $child[$property] ?? 'invalid', $palette[$token], $token);
        }
    }

    expect($wrong)->toBe([]);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// Constitution IX: under a theme with the CoreX roles and no band tokens every property keeps a value.
it('gives every re-pointed property a palette value under a theme with the CoreX roles and no band tokens (FR-013)', function () {
    $palette = sectionStylesNamed('roles');
    $child = bandChild('corex-band-dark', $palette);
    $invalid = [];

    foreach (array_keys(darkBandRepointing()) as $property) {
        if ($property === '--wp--custom--color--accent-subtle') {
            continue; // such a theme has no such token; it stays undefined, as it was
        }
        if (! in_array($child[$property] ?? null, array_values($palette), true)) {
            $invalid[] = $property . ' is ' . ($child[$property] ?? 'invalid');
        }
    }

    expect($invalid)->toBe([]);
});

it('draws the dark band\'s own ground and text from the band tokens, re-pointing nothing on itself', function (string $file) {
    $palette = sectionStylesPalette($file);
    $own = SectionStyleCascade::fromStylesheet()->rule('.wp-block-group.is-style-corex-band-dark');
    $drawn = SectionStyleCascade::fromStylesheet()->draw('corex-band-dark', $palette);

    expect(array_filter(array_keys($own), static fn (string $name): bool => str_starts_with($name, '--wp--')))->toBe([])
        ->and([$drawn['section'], $drawn['text']])->toBe([$palette['custom.band'], $palette['custom.bandInk']]);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// US-BRD-01 scenario 9: a button, a link and a card take the band's colours by their ordinary rules.
it('draws a link, a card and a button inside the dark band in the band\'s colours', function (string $file) {
    $palette = sectionStylesPalette($file);
    $drawn = SectionStyleCascade::fromStylesheet()->draw('corex-band-dark', $palette);

    expect($drawn)->toMatchArray([
        'link'     => $palette['custom.bandLink'],
        'card'     => $palette['custom.bandRaised'],
        'cardEdge' => $palette['custom.bandLine'],
        'cardText' => $palette['custom.bandInk'],
        'secondary'     => $palette['custom.bandRaised'],
        'secondaryText' => $palette['custom.bandInk'],
        'secondaryEdge' => $palette['custom.bandBorder'],
        'button'   => $palette['custom.bandAccent'],
        'label'    => $palette['custom.band'],
        'ring'     => $palette['custom.bandAccent'],
    ]);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// DECISIONS #642: a brand that sets a light on-accent for a dark accent does not reach the band's brass.
it('keeps the dark band\'s button label graphite under a brand with a light on-accent', function () {
    $palette = ['custom.onAccent' => '#ffffff'] + sectionStylesPalette('theme.json');
    $drawn = SectionStyleCascade::fromStylesheet()->draw('corex-band-dark', $palette);

    expect($drawn['label'])->toBe($palette['custom.band'])
        ->and(sectionStylesContrast((string) $drawn['label'], (string) $drawn['button']))->toBeGreaterThanOrEqual(4.5);
});

it('draws the quiet band on the quiet grey ground', function (string $name) {
    $palette = sectionStylesNamed($name);

    expect(SectionStyleCascade::fromStylesheet()->draw('corex-band-quiet', $palette)['section'])->toBe($palette['surface-alt']);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json', 'CoreX roles, no band tokens' => 'roles']);

it('re-points text, links, edges, grounds and the focus ring inside the brass band (FR-013)', function (string $file) {
    $palette = sectionStylesPalette($file);
    $child = bandChild('corex-band-brass', $palette);

    expect(array_map(static fn (string $slug): ?string => $child['--wp--preset--color--' . $slug] ?? null, ['ink', 'ink-soft', 'primary', 'accent-dark', 'border']))
        ->toBe(array_fill(0, 5, $palette['custom.onAccent']))
        ->and([$child['--wp--preset--color--surface'], $child['--wp--preset--color--background']])->toBe([$palette['accent'], $palette['accent']])
        ->and([$child['--wp--preset--color--surface-alt'], $child['--wp--preset--color--surface-strong']])
        ->toBe(array_fill(0, 2, $palette['custom.accentHover']))
        // A card on brass is the kit's near-white card, in both modes.
        ->and($child['--wp--preset--color--surface-raised'])->toBe($palette['custom.bandInk'])
        ->and($child['--wp--custom--focus--color'] ?? null)->toBe($palette['custom.onAccent']);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// DECISIONS #642, the kit's `.corex-section--accent`: the brass band's plain button is graphite.
it('draws the brass band\'s plain button on the on-accent graphite with the band-ink label', function (string $file) {
    $palette = sectionStylesPalette($file);
    $drawn = SectionStyleCascade::fromStylesheet()->draw('corex-band-brass', $palette);

    expect([$drawn['button'], $drawn['label'], $drawn['buttonHover']])
        ->toBe([$palette['custom.onAccent'], $palette['custom.bandInk'], $palette['custom.bandRaised']])
        ->and(sectionStylesContrast((string) $drawn['button'], (string) $drawn['section']))->toBeGreaterThanOrEqual(3.0)
        ->and(sectionStylesContrast((string) $drawn['label'], (string) $drawn['button']))->toBeGreaterThanOrEqual(4.5)
        ->and(sectionStylesContrast((string) $drawn['buttonHover'], (string) $drawn['section']))->toBeGreaterThanOrEqual(3.0)
        ->and(sectionStylesContrast((string) $drawn['label'], (string) $drawn['buttonHover']))->toBeGreaterThanOrEqual(4.5);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// A card and a field in the brass band read, in Dark too; the card is the kit's near-white one.
it('makes a card and a field in the brass band readable in both modes', function (string $file) {
    $palette = sectionStylesPalette($file);
    $drawn = SectionStyleCascade::fromStylesheet()->draw('corex-band-brass', $palette);

    expect([$drawn['card'], $drawn['cardText']])->toBe([$palette['custom.bandInk'], $palette['custom.onAccent']])
        ->and(sectionStylesContrast((string) $drawn['cardText'], (string) $drawn['card']))->toBeGreaterThanOrEqual(4.5)
        ->and(sectionStylesContrast((string) $drawn['fieldText'], (string) $drawn['field']))->toBeGreaterThanOrEqual(4.5)
        ->and(sectionStylesContrast((string) $drawn['fieldEdge'], (string) $drawn['field']))->toBeGreaterThanOrEqual(3.0);
})->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

it('gives a plain element button and an unstyled button block a band\'s button rule, never a disabled one', function () {
    $selectors = bandButtonSelectors();

    expect($selectors)->toHaveCount(2);
    foreach ($selectors as $selector) {
        expect($selector)
            ->toContain(':is(.wp-block-group.is-style-corex-band-dark, .wp-block-group.is-style-corex-band-brass)')
            ->toContain('.wp-element-button:not(.wp-block-button__link, [class*="is-style-"])')
            ->toContain('.wp-block-button:is(:not([class*="is-style-"]), .is-style-fill) > .wp-block-button__link')
            // The newsletter's submit is no element button: without it, brass on brass in the brass band.
            ->toContain('.corex-newsletter-signup__submit')
            ->toContain(':not(:disabled, [aria-disabled="true"])');
    }
});

// The coordinator's E: inside a band a disabled button keeps the theme's disabled look, in band colours.
it('leaves a disabled button in a band to the theme\'s disabled look', function (string $style, string $ground, string $text) {
    foreach (['theme.json', 'styles/dark.json'] as $file) {
        $palette = sectionStylesPalette($file);
        $drawn = SectionStyleCascade::fromStylesheet()->draw($style, $palette);

        expect([$drawn['disabled'], $drawn['disabledText']])->toBe([$palette[$ground], $palette[$text]]);
    }
})->with([
    'dark band' => ['corex-band-dark', 'custom.bandLine', 'custom.bandInkSoft'],
    'brass band' => ['corex-band-brass', 'custom.accentHover', 'custom.onAccent'],
]);

it('leaves a disabled secondary or ghost button to the theme\'s disabled look, as a band\'s button rule does', function () {
    preg_match_all('~([^{}]+)\{~', sectionStylesCss('block-styles.css') . sectionStylesCss('section-bands.css'), $rules);
    $buttons = array_values(preg_grep('/is-style-corex-(?:secondary|ghost)/', $rules[1]));

    expect($buttons)->toHaveCount(5);
    foreach ($buttons as $selector) {
        expect($selector)->toContain('.wp-block-button__link:not(:disabled, [aria-disabled="true"])');
    }
});

// The kit's secondary and ghost (bundle.css 6.1), drawn by the band's re-pointing inside a band.
it('draws the secondary button on the raised surface with the border edge, and transparent on brass', function (string $style, string $file, array $want) {
    $palette = sectionStylesPalette($file);
    $drawn = $style === 'page'
        ? SectionStyleCascade::fromStylesheet()->draw('corex-band-quiet', $palette)
        : SectionStyleCascade::fromStylesheet()->draw($style, $palette);

    expect([$drawn['secondary'], $drawn['secondaryText'], $drawn['secondaryEdge']])
        ->toBe(array_map(static fn (string $key): string => $key === 'transparent' ? $key : $palette[$key], $want));
})->with([
    'on the page, light' => ['page', 'theme.json', ['surface-raised', 'ink', 'border']],
    'on the page, dark' => ['page', 'styles/dark.json', ['surface-raised', 'ink', 'border']],
    'on brass, light' => ['corex-band-brass', 'theme.json', ['transparent', 'custom.onAccent', 'custom.onAccent']],
    'on brass, dark' => ['corex-band-brass', 'styles/dark.json', ['transparent', 'custom.onAccent', 'custom.onAccent']],
]);

// Item 3 of the review: each band keeps its own colours when placed directly inside the other.
it('draws a band placed directly inside the other band in its own readable colours', function (string $inner, string $outer, string $file) {
    $drawn = SectionStyleCascade::fromStylesheet()->draw($inner, sectionStylesPalette($file), $outer);
    $alone = SectionStyleCascade::fromStylesheet()->draw($inner, sectionStylesPalette($file));

    foreach (['section', 'text', 'heading', 'button', 'label'] as $role) {
        expect((string) $drawn[$role])->toMatch('/^#[0-9a-f]{6}$/i', "{$inner} in {$outer}: its {$role}");
    }
    expect(sectionStylesContrast((string) $drawn['text'], (string) $drawn['section']))->toBeGreaterThanOrEqual(4.5)
        ->and(sectionStylesContrast((string) $drawn['heading'], (string) $drawn['section']))->toBeGreaterThanOrEqual(4.5)
        ->and(sectionStylesContrast((string) $drawn['button'], (string) $drawn['section']))->toBeGreaterThanOrEqual(3.0)
        ->and(sectionStylesContrast((string) $drawn['label'], (string) $drawn['button']))->toBeGreaterThanOrEqual(4.5)
        // Inside the other band it takes that band's tokens, never its own ground for its text.
        ->and($drawn['text'])->not->toBe($drawn['section'])
        ->and($drawn['heading'])->not->toBe($drawn['section'])
        ->and($drawn['button'])->not->toBe($drawn['section'])
        ->and($alone['section'])->not->toBeNull();
})->with([
    'brass in dark' => ['corex-band-brass', 'corex-band-dark'],
    'dark in brass' => ['corex-band-dark', 'corex-band-brass'],
])->with(['light' => 'theme.json', 'dark' => 'styles/dark.json']);

// Item 1 of the review: a brand palette of the thirteen roles on the CoreX theme still draws the bands.
it('draws both bands under a brand palette of the thirteen roles, which has no inverse', function (string $style) {
    $palette = sectionStylesNamed('brand');
    $drawn = SectionStyleCascade::fromStylesheet()->draw($style, $palette);

    expect($palette)->not->toHaveKey('inverse');
    foreach (['section', 'text', 'heading', 'button', 'label', 'card', 'cardText', 'disabled', 'disabledText'] as $role) {
        expect((string) $drawn[$role])->toMatch('/^#[0-9a-f]{6}$/i', "{$style}: its {$role}");
    }
    expect(sectionStylesContrast((string) $drawn['text'], (string) $drawn['section']))->toBeGreaterThanOrEqual(4.5)
        ->and(sectionStylesContrast((string) $drawn['button'], (string) $drawn['section']))->toBeGreaterThanOrEqual(3.0)
        ->and(sectionStylesContrast((string) $drawn['label'], (string) $drawn['button']))->toBeGreaterThanOrEqual(4.5)
        ->and(sectionStylesContrast((string) $drawn['cardText'], (string) $drawn['card']))->toBeGreaterThanOrEqual(4.5);
})->with(['corex-band-dark', 'corex-band-brass']);

/*
 * Item 2 of the review: a brand palette replaces the list whole and must name only the thirteen roles.
 * Every other slug the theme's rules or corex-ui's sheets read has a fallback, or is itself the
 * fallback of a custom colour that the CoreX theme keeps.
 *
 * @return list<string> each reference of a slug outside the thirteen roles with nothing to fall to
 */
function sectionStylesBareSlugs(string $css): array
{
    preg_match_all('/(,\s*)?var\(\s*--wp--preset--color--([a-z0-9-]+)\s*([,)])/', $css, $uses, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $bare = [];

    foreach ($uses as $use) {
        $slug = $use[2][0];
        $hasFallback = $use[3][0] === ',';
        $isCustomFallback = ($use[1][0] ?? '') !== ''
            && preg_match('/var\(\s*--wp--custom--color--[a-z0-9-]+\s*$/', substr($css, max(0, $use[0][1] - 80), min(80, $use[0][1]))) === 1;
        if (! in_array($slug, SECTION_STYLES_BRAND_ROLES, true) && ! $hasFallback && ! $isCustomFallback) {
            $bare[] = $slug;
        }
    }

    return array_values(array_unique($bare));
}

it('reads no slug outside the thirteen brand roles without a fallback', function () {
    $theme = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/theme.json'), true);
    $themeCss = json_encode($theme['styles']['elements'] ?? []) . ($theme['styles']['css'] ?? '') . json_encode($theme['styles']['color'] ?? []);

    expect(sectionStylesBareSlugs(stripslashes((string) $themeCss)))->toBe([], 'theme.json')
        ->and(sectionStylesBareSlugs(sectionStylesCss('block-styles.css')))->toBe([], 'block-styles.css')
        ->and(sectionStylesBareSlugs(sectionStylesCss('section-bands.css')))->toBe([], 'section-bands.css');
});

it('finds a slug outside the thirteen roles that has nothing to fall to', function () {
    expect(sectionStylesBareSlugs('a { color: var(--wp--preset--color--selection); }'))->toBe(['selection'])
        ->and(sectionStylesBareSlugs('a { color: var(--wp--preset--color--selection, var(--wp--preset--color--accent)); }'))->toBe([])
        ->and(sectionStylesBareSlugs('a { color: var(--wp--custom--color--on-accent, var(--wp--preset--color--inverse)); }'))->toBe([]);
});

// FR-014: a heading with no colour of its own takes the dark or brass band's text colour.
it('gives a heading with no colour of its own the dark or brass band\'s text colour (FR-014)', function (string $style, string $name) {
    $cascade = SectionStyleCascade::fromStylesheet();
    $drawn = $cascade->draw($style, sectionStylesNamed($name));

    expect($cascade->rule('.wp-block-group.is-style-' . $style . ' :where(h1, h2, h3, h4, h5, h6)'))->toBe(['color' => 'inherit'])
        ->and($drawn['heading'])->not->toBeNull()->toBe($drawn['text']);
})->with(['corex-band-dark', 'corex-band-brass'])
    ->with(['light' => 'theme.json', 'dark' => 'styles/dark.json', 'CoreX roles, no band tokens' => 'roles']);

it('leaves a heading in the quiet band to the theme\'s heading colour', function () {
    expect(SectionStyleCascade::fromStylesheet()->declarations('.is-style-corex-band-quiet', 'h1'))->toBe([]);
});

// DECISIONS #642: the slugs a theme must have for the bands are the ones the band rules fall back to.
it('names as the bands\' condition exactly the palette slugs their rules fall back to', function () {
    $css = sectionStylesCss('section-bands.css');
    preg_match_all('/:\s*([^;{}]*)/', $css, $values);
    preg_match_all('/--wp--preset--color--([a-z0-9-]+)\)/', implode(' ', $values[1]), $slugs);
    $named = array_values(array_unique($slugs[1]));
    sort($named);
    $condition = BlockStyles::BAND_SLUGS;
    sort($condition);

    expect($named)->toBe($condition);
});

it('keeps every band rule in the band sheet, so a theme without the bands meets none', function () {
    expect(sectionStylesCss('block-styles.css'))->not->toContain('corex-band');
});

it('names no raw colour or size in a band or button style (constitution V)', function () {
    $raw = [];
    $rules = [];

    foreach (['section-bands.css', 'block-styles.css'] as $file) {
        preg_match_all('~([^{}]+)\{([^{}]*)\}~', sectionStylesCss($file), $found, PREG_SET_ORDER);
        $rules = [...$rules, ...$found];
    }
    foreach ($rules as [, $selector, $body]) {
        if (preg_match('/corex-band-|corex-secondary|corex-ghost/', $selector) !== 1) {
            continue;
        }
        // Only the values: a property may be named for a slug (`--wp--preset--color--white`).
        $values = (string) preg_replace('/(?:^|;)\s*[\w-]+\s*:/', ';', $body);
        $values = (string) preg_replace('/var\(\s*--[\w-]+/', 'var(', $values);
        if (preg_match_all('/#[0-9a-f]{3,8}\b|\b(?:rgba?|hsla?)\(|\b\d*\.?\d+(?:px|rem|em|%)|\b(?:white|black)\b/i', $values, $found) > 0) {
            $raw[] = trim($selector) . ': ' . implode(', ', $found[0]);
        }
    }

    expect($rules)->not->toBeEmpty()
        ->and($raw)->toBe([]);
});

// FR-011: the kit's secondary (raised ground, border edge) and ghost (ink text, quiet hover).
it('draws the secondary and the ghost button as the kit does (FR-011)', function () {
    $cascade = SectionStyleCascade::fromStylesheet();
    $secondary = $cascade->declarations('.is-style-corex-secondary', '.wp-block-button__link', '!:hover', '!.is-style-corex-band-brass');
    $ghost = $cascade->declarations('.is-style-corex-ghost', '.wp-block-button__link', '!:hover');

    expect($secondary)->toMatchArray([
        'background-color' => 'var(--wp--preset--color--surface-raised, var(--wp--preset--color--surface))',
        'color'            => 'var(--wp--preset--color--ink)',
        'border'           => 'var(--wp--custom--border--width--thin) solid var(--wp--preset--color--border)',
    ])->and($ghost)->toMatchArray([
        'background-color' => 'transparent',
        'color'            => 'var(--wp--preset--color--ink)',
        'border'           => 'var(--wp--custom--border--width--thin) solid transparent',
    ])->and($cascade->declarations('.is-style-corex-ghost', '.wp-block-button__link', ':hover'))
        ->toBe(['background-color' => 'var(--wp--preset--color--surface-alt)'])
        ->and($cascade->declarations('.is-style-corex-secondary', '.wp-block-button__link', ':hover', '!.is-style-corex-band-brass'))
        ->toBe(['background-color' => 'var(--wp--preset--color--surface-alt)', 'border-color' => 'var(--wp--preset--color--ink)']);
});
