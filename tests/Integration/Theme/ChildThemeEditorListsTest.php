<?php

/**
 * What the editor of a child of the CoreX theme sees, on a real WordPress (spec 135, slice 6, G-048;
 * User Story 5; SC-006; DECISIONS #664, #668).
 *
 * Each test writes a small child of the CoreX theme into a theme root of its own, switches to it
 * and switches back in `afterEach`, so what is measured is WordPress's own pattern registry, template
 * lists and theme JSON merge with the switch set. Patterns are registered by running the callbacks
 * WordPress has on `init`, in their priority order, as a request does.
 *
 * @package Corex\Tests\Integration\Theme
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Site\ParentThemeTokens;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Theme\ParentPatternsHider;

/**
 * Write a child of the CoreX theme and make it the active theme.
 *
 * @param array<string, mixed>|null $themeJson The child's theme.json, or null for none.
 * @param array<string, string>     $files     More files, by theme-relative path.
 * @return array{root: string, stylesheet: string}
 */
function corexListsChild(?array $themeJson, array $files = []): array
{
    $root = sys_get_temp_dir() . '/corex-lists-root-' . uniqid('', true);
    $stylesheet = 'corex-lists-child-' . substr(md5($root), 0, 8);
    $dir = $root . '/' . $stylesheet;

    $files = [
        'style.css' => "/*\nTheme Name: Corex Lists Child\nTemplate: corex\nText Domain: {$stylesheet}\n*/\n",
        'templates/front-page.html' => "<!-- wp:paragraph -->\n<p>The child's own front page</p>\n<!-- /wp:paragraph -->\n",
        'patterns/own.php' => "<?php\n/**\n * Title: The child's own\n * Slug: corex-lists/own\n */\n?>\n<!-- wp:paragraph --><p>Own</p><!-- /wp:paragraph -->\n",
        // The child's own file for a slug the CoreX theme's folder also declares: the child's wins.
        'patterns/loading.php' => "<?php\n/**\n * Title: The child's loading\n * Slug: corex/loading\n */\n?>\n<!-- wp:paragraph --><p>Loading</p><!-- /wp:paragraph -->\n",
        ...$files,
    ];
    if ($themeJson !== null) {
        $files['theme.json'] = (string) wp_json_encode(['version' => 3, ...$themeJson]);
    }

    foreach ($files as $relative => $contents) {
        $path = $dir . '/' . $relative;
        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, $contents);
    }

    register_theme_directory($root);
    wp_clean_themes_cache();
    switch_theme($stylesheet);
    WP_Theme_JSON_Resolver::clean_cached_data();

    return ['root' => $root, 'stylesheet' => $stylesheet];
}

/** The switches as a child's theme.json sets them. */
function corexListsSwitches(array $parent): array
{
    return ['settings' => ['custom' => ['corex' => ['parent' => $parent]]]];
}

/** @return list<string> The slugs the CoreX theme's own `patterns/` folder declares. */
function corexListsCorexPatternSlugs(): array
{
    return ParentPatternsHider::slugs(wp_get_theme('corex')->get_block_patterns());
}

/** @return int How many pattern files the CoreX theme's folder holds. */
function corexListsCorexPatternFiles(): int
{
    return count(glob(dirname(__DIR__, 3) . '/theme/patterns/*.php') ?: []);
}

/**
 * The CoreX theme's custom templates, name => title, as its theme.json lists them.
 *
 * @return array<string, string>
 */
function corexListsCorexCustomTitles(): array
{
    $entries = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/theme.json'), true)['customTemplates'];

    return array_column($entries, 'title', 'name');
}

/**
 * Register the theme folders' patterns as a request on the active theme does: the theme folders'
 * patterns are dropped, then WordPress's `init` registration runs, then, for a request that serves the
 * REST API the editors read, every `rest_api_init` callback of the hider (DECISIONS #668: the
 * inserter flag is set only where patterns are chosen).
 */
function corexListsRegisterThemePatterns(bool $servingRest = true): void
{
    $registry = WP_Block_Patterns_Registry::get_instance();
    foreach ([...corexListsCorexPatternSlugs(), 'corex-lists/own'] as $slug) {
        if ($registry->is_registered($slug)) {
            $registry->unregister($slug);
        }
    }

    $init = $GLOBALS['wp_filter']['init'] ?? null;
    expect($init)->toBeInstanceOf(WP_Hook::class);

    $callbacks = $init->callbacks;
    ksort($callbacks);
    foreach ($callbacks as $hooked) {
        foreach ($hooked as $callback) {
            $function = $callback['function'];
            if ($function === '_register_theme_block_patterns') {
                call_user_func($function);
            }
        }
    }

    if (! $servingRest) {
        return;
    }

    foreach (($GLOBALS['wp_filter']['rest_api_init'] ?? null)?->callbacks ?? [] as $hooked) {
        foreach ($hooked as $callback) {
            $function = $callback['function'];
            if (is_array($function) && $function[0] instanceof ParentPatternsHider) {
                call_user_func($function);
            }
        }
    }
}

/**
 * Every registered pattern's `inserter`, by name (true when the pattern does not set it).
 *
 * @return array<string, bool>
 */
function corexListsInserter(): array
{
    $inserter = [];
    foreach (WP_Block_Patterns_Registry::get_instance()->get_all_registered() as $pattern) {
        $inserter[$pattern['name']] = ($pattern['inserter'] ?? true) !== false;
    }

    return $inserter;
}

/** Remove a folder and everything in it. */
function corexListsRemove(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($items as $item) {
        $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    }
    rmdir($dir);
}

/** Act as an administrator of the install. */
function corexListsAsAdministrator(): void
{
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    expect($admins)->not->toBe([]);
    wp_set_current_user((int) $admins[0]);
}

/** A REST GET answered as WordPress answers the editor. */
function corexListsRest(string $route, array $params = []): mixed
{
    $request = new WP_REST_Request('GET', $route);
    foreach ($params as $key => $value) {
        $request->set_param($key, $value);
    }
    $response = rest_do_request($request);

    expect($response->get_status())->toBe(200);

    return $response->get_data();
}

beforeEach(function () {
    $this->previousStylesheet = get_stylesheet();
    $this->previousUser = get_current_user_id();
    $this->roots = [];
    $this->stylesheets = [];
});

afterEach(function () {
    global $wp_theme_directories;

    $registry = WP_Block_Patterns_Registry::get_instance();
    if ($registry->is_registered('corex-lists/own')) {
        $registry->unregister('corex-lists/own');
    }

    switch_theme($this->previousStylesheet);
    $roots = array_map('wp_normalize_path', $this->roots);
    $wp_theme_directories = array_values(array_filter(
        (array) $wp_theme_directories,
        static fn (string $directory): bool => ! in_array(wp_normalize_path($directory), $roots, true),
    ));
    wp_clean_themes_cache();
    WP_Theme_JSON_Resolver::clean_cached_data();

    // The install's own theme's patterns back as its requests register them.
    corexListsRegisterThemePatterns();
    wp_set_current_user($this->previousUser);

    foreach ($this->stylesheets as $stylesheet) {
        delete_option('theme_mods_' . $stylesheet);
    }
    foreach ($this->roots as $root) {
        corexListsRemove($root);
    }
});

/** Write a child, make it active, and have the test remove it afterwards. */
function corexListsUse(object $test, ?array $themeJson, array $files = []): array
{
    $child = corexListsChild($themeJson, $files);
    $test->roots[] = $child['root'];
    $test->stylesheets[] = $child['stylesheet'];

    return $child;
}

it('keeps every pattern of the CoreX theme\'s folder registered and out of the inserter, and the child\'s and corex-ui\'s as they are (FR-021, SC-006)', function () {
    corexListsUse($this, corexListsSwitches(['hidePatterns' => true]));
    corexListsRegisterThemePatterns();

    $corex = corexListsCorexPatternSlugs();
    $inserter = corexListsInserter();
    $hidden = array_keys(array_filter($inserter, static fn (bool $shown): bool => ! $shown));
    sort($hidden);
    $expected = array_values(array_diff($corex, ['corex/loading']));
    sort($expected);

    expect($corex)->toHaveCount(corexListsCorexPatternFiles())
        // Every CoreX pattern is still registered, so a template that inserts one draws it.
        ->and(array_diff($corex, array_keys($inserter)))->toBe([])
        ->and($hidden)->toBe($expected)
        // The child's own file for a CoreX slug is the child's, and is offered.
        ->and($inserter['corex/loading'])->toBeTrue()
        ->and(WP_Block_Patterns_Registry::get_instance()->get_registered('corex/loading')['title'])->toBe('The child\'s loading')
        ->and($inserter['corex-lists/own'])->toBeTrue()
        // corex-ui's `corex/*` patterns are not the CoreX theme's folder's.
        ->and($inserter['corex/hero'])->toBeTrue();

    // The editor's inserter reads this route: it still lists them, marked out of the inserter.
    corexListsAsAdministrator();
    $rest = [];
    foreach (corexListsRest('/wp/v2/block-patterns/patterns') as $pattern) {
        // The route leaves `inserter` out for a pattern that does not set it: it is offered.
        $rest[$pattern['name']] = ($pattern['inserter'] ?? true) !== false;
    }
    expect(array_intersect_key($rest, array_flip($corex)))->toBe(array_intersect_key($inserter, array_flip($corex)))
        ->and($rest['corex/section-services-grid'] ?? null)->toBeFalse()
        ->and($rest['corex/loading'] ?? null)->toBeTrue()
        ->and($rest['corex/hero'] ?? null)->toBeTrue();
});

it('still draws a CoreX template\'s CoreX patterns, in the editor and for a visitor, under a child that hides them (FR-021, User Story 5.2)', function () {
    $child = corexListsUse($this, corexListsSwitches(['hidePatterns' => true]));
    corexListsRegisterThemePatterns();
    corexListsAsAdministrator();

    // The Site Editor's copy: WordPress resolves each pattern block into the pattern's blocks.
    $template = corexListsRest('/wp/v2/templates/' . $child['stylesheet'] . '//page-services', ['context' => 'edit']);
    $raw = (string) ($template['content']['raw'] ?? '');

    expect($raw)->not->toContain('wp:pattern')
        ->and($raw)->toContain('corex-services__grid')
        ->and($raw)->toContain('corex-process');

    // A visitor's page, drawn from the same template.
    wp_set_current_user(0);
    $block = get_block_template($child['stylesheet'] . '//page-services');
    expect($block)->toBeInstanceOf(WP_Block_Template::class);
    $html = do_blocks($block->content);

    expect($html)->toContain('corex-services__grid')
        ->and($html)->toContain('corex-process');
});

it('leaves the patterns as they are on a visitor\'s request, which is not where patterns are chosen (DECISIONS #668)', function () {
    corexListsUse($this, corexListsSwitches(['hidePatterns' => true]));
    corexListsRegisterThemePatterns(false);

    // The `init` hook ran on a request that is neither an admin screen nor the REST API.
    foreach ($GLOBALS['wp_filter']['init']->callbacks[11] ?? [] as $callback) {
        if ($callback['function'] instanceof Closure) {
            call_user_func($callback['function']);
        }
    }

    $inserter = corexListsInserter();

    expect(array_filter(
        array_intersect_key($inserter, array_flip(corexListsCorexPatternSlugs())),
        static fn (bool $shown): bool => ! $shown,
    ))->toBe([]);
});

it('leaves every pattern in the inserter for a child that does not set hidePatterns (FR-021)', function () {
    corexListsUse($this, corexListsSwitches([]));
    corexListsRegisterThemePatterns();

    $inserter = corexListsInserter();

    expect(array_filter(
        array_intersect_key($inserter, array_flip(corexListsCorexPatternSlugs())),
        static fn (bool $shown): bool => ! $shown,
    ))->toBe([]);
});

it('leaves everything as it was under the CoreX theme itself (User Story 5.4)', function () {
    switch_theme('corex');
    WP_Theme_JSON_Resolver::clean_cached_data();
    corexListsRegisterThemePatterns();

    expect(array_filter(
        array_intersect_key(corexListsInserter(), array_flip(corexListsCorexPatternSlugs())),
        static fn (bool $shown): bool => ! $shown,
    ))->toBe([])
        ->and(wp_get_theme()->get_page_templates(null, 'page'))->toHaveKeys(array_keys(corexListsCorexCustomTitles()));
});

it('changes no template list or page template choice for a child that sets hideTemplates, which was withdrawn (DECISIONS #668)', function () {
    corexListsAsAdministrator();

    $measure = static function (): array {
        return [
            'templates' => array_column(corexListsRest('/wp/v2/templates'), 'slug'),
            'parts' => array_column(corexListsRest('/wp/v2/template-parts'), 'slug'),
            'choices' => array_keys(wp_get_theme()->get_page_templates(null, 'page')),
        ];
    };

    corexListsUse($this, corexListsSwitches([]));
    $without = $measure();

    corexListsUse($this, corexListsSwitches(['hideTemplates' => true]));
    $with = $measure();

    expect($with)->toBe($without)
        ->and($with['templates'])->toContain('page-landing')
        ->and($with['parts'])->toContain('header')
        ->and($with['choices'])->toContain('page-landing');
});

it('offers CoreX\'s custom templates with their titles beside the child\'s own (FR-023, User Story 5.3)', function () {
    corexListsUse(
        $this,
        ['customTemplates' => [['name' => 'page-acme', 'title' => 'Acme page']]],
        ['templates/page-acme.html' => "<!-- wp:post-content /-->\n"],
    );

    $corex = corexListsCorexCustomTitles();
    $expected = [];
    foreach ($corex as $name => $title) {
        $expected[$name] = ['title' => $title, 'postTypes' => ['page']];
    }
    $expected['page-acme'] = ['title' => 'Acme page', 'postTypes' => ['page']];

    // What WordPress merged: CoreX's entries as CoreX lists them, then the child's own.
    expect(WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => false])->get_custom_templates())->toBe($expected)
        ->and(array_intersect_key(wp_get_theme()->get_page_templates(null, 'page'), $expected))
        ->toEqual(array_map(static fn (array $entry): string => $entry['title'], $expected));
});

it('keeps CoreX\'s custom template titles in the site\'s language beside the child\'s own (FR-023)', function () {
    $catalogue = dirname(__DIR__, 3) . '/plugins/corex-core/languages/corex-ar.mo';
    expect(is_readable($catalogue))->toBeTrue();
    unload_textdomain('corex');
    expect(load_textdomain('corex', $catalogue))->toBeTrue();

    try {
        corexListsUse(
            $this,
            ['customTemplates' => [['name' => 'page-acme', 'title' => 'Acme page', 'postTypes' => ['page']]]],
            ['templates/page-acme.html' => "<!-- wp:post-content /-->\n"],
        );

        $expected = [];
        foreach (corexListsCorexCustomTitles() as $name => $title) {
            // The context WordPress's theme.json i18n schema gives a custom template's title.
            $expected[$name] = translate_with_gettext_context($title, 'Custom template name', 'corex');
            expect($expected[$name])->not->toBe($title);
        }
        $expected['page-acme'] = 'Acme page';

        expect(wp_list_pluck(WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => false])->get_custom_templates(), 'title'))
            ->toBe($expected);
    } finally {
        unload_textdomain('corex');
        WP_Theme_JSON_Resolver::clean_cached_data();
    }
});

it('keeps a child\'s own title for a CoreX custom template (FR-023)', function () {
    corexListsUse($this, ['customTemplates' => [['name' => 'page-contact', 'title' => 'Talk to Acme', 'postTypes' => ['page']]]]);

    $expected = corexListsCorexCustomTitles();
    $expected['page-contact'] = 'Talk to Acme';

    expect(array_column(WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => false])->get_custom_templates(), 'title'))
        ->toBe(array_values($expected));
});

it('gives a child with an empty list, or none, CoreX\'s custom templates (FR-023)', function () {
    $corex = array_values(corexListsCorexCustomTitles());

    corexListsUse($this, ['customTemplates' => []]);
    $empty = array_column(WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => false])->get_custom_templates(), 'title');

    corexListsUse($this, ['settings' => []]);
    $none = array_column(WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => false])->get_custom_templates(), 'title');

    expect($empty)->toBe($corex)
        ->and($none)->toBe($corex);
});

it('generates a client theme that does not set the switch, so a new site shows everything (FR-024)', function () {
    $root = sys_get_temp_dir() . '/corex-lists-site-' . uniqid('', true);
    mkdir($root);
    $this->roots[] = $root;
    $repository = dirname(__DIR__, 3);

    (new SiteScaffolder(
        new StubRenderer(),
        $repository . '/packages/cli/stubs',
        new ParentThemeTokens([$repository . '/theme/theme.json']),
    ))->scaffold('Initech', $root, []);

    $themeJson = json_decode((string) file_get_contents($root . '/initech-theme/theme.json'), true);
    $readme = (string) file_get_contents($root . '/README.md');

    expect($themeJson['settings']['custom']['corex']['parent'] ?? null)->toBeNull()
        ->and($readme)->toContain('settings.custom.corex.parent.hidePatterns')
        ->and($readme)->not->toContain('hideTemplates');
});
