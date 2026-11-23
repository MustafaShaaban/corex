<?php

/**
 * The four mega menu patterns are panels (spec 137 slice 3; US-NAV-02 criterion 5 as corrected,
 * FR-019, FR-020; DECISIONS #691; first shipped by spec 058 US2).
 *
 * Each is the content of a panel, a template part of the `mega-menu` area, under its old slug: no
 * `<details>` disclosure, offered where WordPress offers patterns for that area, and described with
 * the steps that put it on a mega menu item. Those steps name the editor's own words, read here from
 * the block and its controls, so the sentence stays true when either changes. Headers saved earlier
 * keep their `details.corex-mega` panels: the script and the styles that serve them stay.
 *
 * Raw-literal freedom is covered by NavigationPatternsTest's patterns/*.php scan.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

/** @return array<string, array{0: string}> */
function corexMegaVariants(): array
{
    return [
        'simple' => ['simple'],
        'services' => ['services'],
        'product' => ['product'],
        'docs' => ['docs'],
    ];
}

function corexMegaPattern(string $variant): string
{
    $file = ThemeContract::root() . "/theme/patterns/megamenu-{$variant}.php";
    expect($file)->toBeFile();

    return (string) file_get_contents($file);
}

/** A header field of a theme pattern file. */
function corexMegaHeader(string $pattern, string $field): string
{
    preg_match('/^\s*\*\s*' . preg_quote($field, '/') . ':\s*(.+)$/m', $pattern, $match);

    return trim($match[1] ?? '');
}

/** The editor's words the descriptions name, as the editor prints them. */
function corexMegaEditorWords(): array
{
    $root = ThemeContract::root() . '/addons/corex-ui/src/Blocks/mega-menu';
    $controls = (string) file_get_contents($root . '/panel-controls.js');
    $title = json_decode((string) file_get_contents($root . '/block.json'), true)['title'] ?? '';

    preg_match_all("/__\(\s*'([^']+)',\s*'corex'\s*\)/", $controls, $strings);

    return ['title' => $title, 'strings' => $strings[1]];
}

it('FR-019: ships every mega menu pattern under its old slug and the CoreX category', function (string $variant) {
    $pattern = corexMegaPattern($variant);

    expect(corexMegaHeader($pattern, 'Slug'))->toBe("corex/megamenu-{$variant}")
        ->and(corexMegaHeader($pattern, 'Categories'))->toContain('corex');
})->with(corexMegaVariants());

it('FR-019: makes each one a panel for the mega-menu area, with no details disclosure', function (string $variant) {
    $pattern = corexMegaPattern($variant);

    expect(corexMegaHeader($pattern, 'Block Types'))->toBe('core/template-part/mega-menu')
        ->and($pattern)->not->toContain('wp:details')
        ->and($pattern)->not->toContain('<summary')
        ->and($pattern)->not->toContain('corex-mega ')
        ->and($pattern)->toMatch('/<!-- wp:group \{[^}]*"className":"corex-megamenu[ "]/');
})->with(corexMegaVariants());

it('FR-019, US-NAV-02 criterion 5: describes how to put the panel on a mega menu item, in the editor\'s own words', function (string $variant) {
    $description = corexMegaHeader(corexMegaPattern($variant), 'Description');
    $editor = corexMegaEditorWords();

    // The words a person is told to look for are the ones the editor shows.
    expect($editor['title'])->toBe('Mega menu item')
        ->and($editor['strings'])->toContain('New panel', 'Start from', 'Create panel')
        ->and($description)->toContain('CoreX UI add-on')
        ->and($description)->toContain($editor['title'])
        ->and($description)->toContain('New panel')
        ->and($description)->toContain('Create panel')
        ->and($description)->not->toContain('Insert into a header');
})->with(corexMegaVariants());

it('FR-019: writes no em dash in a title or a description', function (string $variant) {
    $pattern = corexMegaPattern($variant);

    expect(corexMegaHeader($pattern, 'Title') . corexMegaHeader($pattern, 'Description'))->not->toContain("\u{2014}");
})->with(corexMegaVariants());

it('FR-020: keeps the script and the styles that serve headers saved with details panels', function () {
    $root = ThemeContract::root() . '/theme/assets';

    expect((string) file_get_contents($root . '/js/corex-navigation.js'))->toContain("'details.corex-mega'")
        ->and((string) file_get_contents($root . '/css/corex-navigation.css'))->toContain('.corex-mega > summary')
        ->and((string) file_get_contents($root . '/css/corex-navigation-collapse.css'))->toContain('.corex-mega[open] > .corex-mega__panel');
});
