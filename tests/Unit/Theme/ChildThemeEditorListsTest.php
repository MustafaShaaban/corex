<?php

/**
 * What the editor of a child of the CoreX theme sees (spec 135, slice 6, G-048; DECISIONS #664):
 * the two switches a child sets in its own theme.json, the editor lists they shorten, and the
 * custom templates completed before WordPress merges them with the CoreX theme's.
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
use Corex\Theme\ParentTemplatesHider;

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

/** A theme.json that sets the two switches as given. */
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
    Functions\when('get_block_theme_folders')->justReturn(['wp_template' => 'templates', 'wp_template_part' => 'parts']);
}

/** A template as WordPress's block template functions hand it over. */
function child_lists_template(string $slug, string $source = 'theme', string $type = 'wp_template'): object
{
    return (object) ['slug' => $slug, 'source' => $source, 'type' => $type];
}

it('reads both switches from a child of the CoreX theme\'s own theme.json (FR-021, FR-022, FR-024)', function () {
    $child = child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hidePatterns' => true, 'hideTemplates' => true])]);
    child_lists_as('acme-theme', 'corex', $child, child_lists_theme_dir([]));

    $switches = new ChildThemeSwitches();

    expect($switches->hides(ChildThemeSwitches::PATTERNS))->toBeTrue()
        ->and($switches->hides(ChildThemeSwitches::TEMPLATES))->toBeTrue();
});

it('is off for a child that sets neither switch, or sets one to something other than true (FR-021, FR-022)', function () {
    $child = child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hidePatterns' => 'true'])]);
    child_lists_as('acme-theme', 'corex', $child, child_lists_theme_dir([]));

    $switches = new ChildThemeSwitches();

    expect($switches->hides(ChildThemeSwitches::PATTERNS))->toBeFalse()
        ->and($switches->hides(ChildThemeSwitches::TEMPLATES))->toBeFalse();
});

it('never turns on from the CoreX theme\'s own theme.json, active or as the parent (FR-024)', function () {
    $corex = child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hidePatterns' => true, 'hideTemplates' => true])]);

    // The CoreX theme itself is active.
    child_lists_as('corex', 'corex', $corex, $corex);
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();

    // A child with no theme.json of its own: WordPress would hand it the parent's file.
    child_lists_as('acme-theme', 'corex', child_lists_theme_dir([]), $corex);
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::TEMPLATES))->toBeFalse();
});

it('is off for a standalone theme and for a child of another theme, whatever their theme.json says (FR-021)', function () {
    $own = child_lists_theme_dir(['theme.json' => child_lists_theme_json(['hidePatterns' => true, 'hideTemplates' => true])]);

    child_lists_as('muva-theme', 'muva-theme', $own, $own);
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();

    child_lists_as('acme-theme', 'twentytwentyfive', $own, child_lists_theme_dir([]));
    expect((new ChildThemeSwitches())->hides(ChildThemeSwitches::PATTERNS))->toBeFalse();
});

it('leaves out of the editor\'s lists a template only the CoreX theme has, and keeps the child\'s own (FR-022)', function () {
    $child = child_lists_theme_dir([
        'theme.json' => child_lists_theme_json(['hideTemplates' => true]),
        'templates/front-page.html' => '<!-- wp:paragraph --><p>Acme</p><!-- /wp:paragraph -->',
    ]);
    $parent = child_lists_theme_dir([
        'templates/front-page.html' => '',
        'templates/index.html' => '',
        'parts/header.html' => '',
    ]);
    child_lists_as('acme-theme', 'corex', $child, $parent);

    $hider = new ParentTemplatesHider(new ChildThemeSwitches());
    $templates = [
        child_lists_template('front-page'),
        child_lists_template('index'),
        child_lists_template('index-edited', 'custom'),
        child_lists_template('coming-soon', 'plugin'),
    ];

    expect(array_column($hider->withoutParentOnly($templates, [], 'wp_template'), 'slug'))
        ->toBe(['front-page', 'index-edited', 'coming-soon'])
        ->and($hider->withoutParentOnly([child_lists_template('header', 'theme', 'wp_template_part')], [], 'wp_template_part'))
        ->toBe([]);
});

it('takes a template only the CoreX theme has out of the page template choices, but never the page\'s own (FR-022)', function () {
    $child = child_lists_theme_dir([
        'theme.json' => child_lists_theme_json(['hideTemplates' => true]),
        'templates/page-acme.html' => '',
    ]);
    $parent = child_lists_theme_dir(['templates/page-landing.html' => '', 'templates/page-contact.html' => '']);
    child_lists_as('acme-theme', 'corex', $child, $parent);

    $hider = new ParentTemplatesHider(new ChildThemeSwitches());
    $choices = ['page-landing' => 'Landing page', 'page-contact' => 'Contact page', 'page-acme' => 'Acme page'];

    expect($hider->pickerChoices($choices, null, null))->toBe(['page-acme' => 'Acme page'])
        // A page already drawn by Landing page keeps it among its choices, so saving it never resets it.
        ->and($hider->pickerChoices($choices, null, (object) ['page_template' => 'page-landing']))
        ->toBe(['page-landing' => 'Landing page', 'page-acme' => 'Acme page']);
});

it('changes no list without the switch (FR-022)', function () {
    $child = child_lists_theme_dir(['theme.json' => child_lists_theme_json([])]);
    $parent = child_lists_theme_dir(['templates/index.html' => '']);
    child_lists_as('acme-theme', 'corex', $child, $parent);

    $hider = new ParentTemplatesHider(new ChildThemeSwitches());
    $templates = [child_lists_template('index')];

    expect($hider->withoutParentOnly($templates, [], 'wp_template'))->toBe($templates)
        ->and($hider->pickerChoices(['page-landing' => 'Landing page'], null, null))->toBe(['page-landing' => 'Landing page']);
});

/** The CoreX theme's six custom templates, as its theme.json lists them. */
function child_lists_corex_custom_templates(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/theme.json'), true)['customTemplates'];
}

it('completes a child\'s custom templates with every CoreX entry it does not name, in CoreX\'s order (FR-023)', function () {
    $corex = child_lists_corex_custom_templates();
    $own = ['name' => 'page-acme', 'title' => 'Acme page'];

    $completed = ChildCustomTemplates::complete([$own], $corex);

    // CoreX's entries keep their places and the child's own come after them: WordPress merges the
    // two lists by position, so an entry placed at a CoreX index would take keys it omits from it.
    expect($completed)->toBe([...$corex, $own])
        ->and(array_column($completed, 'name'))->toHaveCount(7);
});

it('keeps a child\'s own entry for a CoreX template, in CoreX\'s place (FR-023)', function () {
    $corex = child_lists_corex_custom_templates();
    $renamed = ['name' => 'page-contact', 'title' => 'Talk to Acme', 'postTypes' => ['page']];

    $completed = ChildCustomTemplates::complete([$renamed], $corex);
    $at = array_search('page-contact', array_column($corex, 'name'), true);

    expect($completed)->toHaveCount(count($corex))
        ->and($completed[$at])->toBe($renamed)
        ->and(array_column($completed, 'name'))->toBe(array_column($corex, 'name'));
});
