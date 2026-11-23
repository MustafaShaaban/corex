<?php

/**
 * What the editor of a child of the CoreX theme sees (spec 135, slice 6, G-048; DECISIONS #664, #668):
 * the patterns switch a child sets in its own theme.json, and the custom templates completed before
 * WordPress merges them with the CoreX theme's.
 *
 * Headless: the theme folders are temporary directories and WordPress's theme functions are
 * stubbed to name them. The integration suite proves the same against a real WordPress.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Theme\ChildCustomTemplates;
use Corex\Theme\ChildThemeSwitches;

/** A temporary theme folder holding the given files (relative path => contents). */
function child_lists_theme_dir(array $files): string
{
    $dir = sys_get_temp_dir() . '/corex-child-lists-' . uniqid('', true);

    foreach ($files as $relative => $contents) {
        $path = $dir . '/' . $relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }

    if (! is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $GLOBALS['corex_child_lists_dirs'][] = $dir;

    return $dir;
}

/** Remove a folder and everything in it. */
function child_lists_remove(string $dir): void
{
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

afterEach(function () {
    foreach ($GLOBALS['corex_child_lists_dirs'] ?? [] as $dir) {
        child_lists_remove($dir);
    }
    $GLOBALS['corex_child_lists_dirs'] = [];
});

/** A theme.json that sets the switches as given. */
function child_lists_theme_json(array $parent): string
{
    return (string) json_encode(['version' => 3, 'settings' => ['custom' => ['corex' => ['parent' => $parent]]]]);
}

/** WordPress's theme functions answering for a stylesheet and a template in these folders. */
function child_lists_as(string $stylesheet, string $template, string $childDir, string $parentDir): void
{
    Functions\when('get_stylesheet')->justReturn($stylesheet);
    Functions\when('get_template')->justReturn($template);
    Functions\when('get_stylesheet_directory')->justReturn($childDir);
    Functions\when('get_template_directory')->justReturn($parentDir);
    Functions\when('wp_json_file_decode')->alias(
        static fn (string $file, array $options = []): mixed => json_decode((string) file_get_contents($file), true),
    );
}

it('reads hidePatterns from a child of the CoreX theme\'s own theme.json (FR-021, FR-024)', function () {
    $child = child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hidePatterns' => true])]);
    child_lists_as('acme-theme', 'corex', $child, child_lists_theme_dir([]));

    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeTrue();
});

it('is off for a child that does not set hidePatterns, or sets it to something other than true (FR-021)', function () {
    child_lists_as('acme-theme', 'corex', child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hidePatterns' => 'true'])]), child_lists_theme_dir([]));
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();

    // The withdrawn template switch turns nothing on (DECISIONS #668).
    child_lists_as('acme-theme', 'corex', child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hideTemplates' => true])]), child_lists_theme_dir([]));
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();
});

it('never turns on from the CoreX theme\'s own theme.json, active or as the parent (FR-024)', function () {
    $corex = child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hidePatterns' => true])]);

    // The CoreX theme itself is active.
    child_lists_as('corex', 'corex', $corex, $corex);
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();

    // A child with no theme.json of its own: WordPress would hand it the parent's file.
    child_lists_as('acme-theme', 'corex', child_lists_theme_dir([]), $corex);
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();
});

it('is off for a standalone theme and for a child of another theme, whatever their theme.json says (FR-021)', function () {
    $own = child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hidePatterns' => true])]);

    child_lists_as('muva-theme', 'muva-theme', $own, $own);
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();

    child_lists_as('acme-theme', 'twentytwentyfive', $own, child_lists_theme_dir([]));
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();
});

/** The CoreX theme's custom templates, as its theme.json lists them. */
function child_lists_corex_custom_templates(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/theme.json'), true)['customTemplates'];
}

/** @return list<array{name: string}> Only the name of each CoreX entry, so WordPress's merge keeps the rest. */
function child_lists_names_only(array $corex): array
{
    return array_map(static fn (array $entry): array => ['name' => $entry['name']], $corex);
}

it('completes a child\'s custom templates with a name-only entry for every CoreX one it does not name, in CoreX\'s order (FR-023)', function () {
    $corex = child_lists_corex_custom_templates();
    $own = ['name' => 'page-acme', 'title' => 'Acme page'];

    $completed = ChildCustomTemplates::complete([$own], $corex);

    // Each CoreX place holds only its name: WordPress merges the lists by position and key, so the
    // CoreX theme's title (translated in its own text domain) and post types are kept. The child's
    // own come after them, where they take no key they omit from a CoreX entry.
    expect($completed)->toBe([...child_lists_names_only($corex), $own]);
});

it('keeps a child\'s own entry for a CoreX template whole, in CoreX\'s place (FR-023)', function () {
    $corex = child_lists_corex_custom_templates();
    $renamed = ['name' => 'page-contact', 'title' => 'Talk to Acme', 'postTypes' => ['page']];

    $completed = ChildCustomTemplates::complete([$renamed], $corex);
    $at = array_search('page-contact', array_column($corex, 'name'), true);
    $expected = child_lists_names_only($corex);
    $expected[$at] = $renamed;

    expect($completed)->toBe($expected);
});

it('turns an empty list into CoreX\'s names only (FR-023)', function () {
    $corex = child_lists_corex_custom_templates();

    expect(ChildCustomTemplates::complete([], $corex))->toBe(child_lists_names_only($corex));
});
