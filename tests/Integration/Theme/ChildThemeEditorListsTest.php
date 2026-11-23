<?php

/**
 * What the editor of a child of the CoreX theme sees, on a real WordPress (spec 135, slice 6, G-048;
 * User Story 5; SC-006; DECISIONS #664).
 *
 * Each test writes a small child of the CoreX theme into a theme root of its own, switches to it
 * and switches back in `finally`, as ComingSoonTemplateResolutionTest does, so what is measured is
 * WordPress's own registry, template lists and theme JSON merge with the switches set.
 *
 * @package Corex\Tests\Integration\Theme
 */

declare(strict_types=1);

use Corex\Boot;
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
    return ParentPatternsHider::slugs(wp_get_theme(get_template())->get_block_patterns());
}

/** @return list<string> Every registered pattern's name. */
function corexListsRegistered(): array
{
    return array_column(WP_Block_Patterns_Registry::get_instance()->get_all_registered(), 'name');
}

/** Register the theme folders' patterns the way a request on the active theme would. */
function corexListsRegisterThemePatterns(): void
{
    $registry = WP_Block_Patterns_Registry::get_instance();
    foreach ([...corexListsCorexPatternSlugs(), 'corex-lists/own'] as $slug) {
        if ($registry->is_registered($slug)) {
            $registry->unregister($slug);
        }
    }
    _register_theme_block_patterns();
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

/**
 * The slugs a Site Editor collection route lists, asked as an administrator.
 *
 * @return list<string>
 */
function corexListsRest(string $route): array
{
    $response = rest_do_request(new WP_REST_Request('GET', $route));

    expect($response->get_status())->toBe(200);

    return array_column($response->get_data(), 'slug');
}

beforeEach(function () {
    $this->previousStylesheet = get_stylesheet();
    $this->previousUser = get_current_user_id();
    $this->roots = [];
    $this->created = [];
});

afterEach(function () {
    global $wp_theme_directories;

    foreach ($this->created as $id) {
        wp_delete_post($id, true);
    }

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

    foreach ($this->roots as $root) {
        corexListsRemove($root);
    }
});

it('unregisters every pattern of the CoreX theme\'s folder and keeps the child\'s and corex-ui\'s (FR-021, SC-006)', function () {
    $child = corexListsChild(corexListsSwitches(['hidePatterns' => true]));
    $this->roots[] = $child['root'];
    corexListsRegisterThemePatterns();

    $corex = corexListsCorexPatternSlugs();
    $before = corexListsRegistered();
    $others = array_values(array_diff($before, $corex));

    Boot::app()->container()->make(ParentPatternsHider::class)->hide();
    $after = corexListsRegistered();

    expect($corex)->toHaveCount(26)
        ->and(array_values(array_intersect($after, $corex)))->toBe(['corex/loading'])
        ->and(WP_Block_Patterns_Registry::get_instance()->get_registered('corex/loading')['title'])->toBe('The child\'s loading')
        ->and($after)->toContain('corex-lists/own')
        ->and(array_values(array_diff($after, $corex)))->toBe($others);

    // corex-ui's `corex/*` patterns are not the CoreX theme's folder's, and stay.
    expect($others)->toContain('corex/hero');
});

it('leaves every pattern registered for a child that does not set hidePatterns (FR-021)', function () {
    $child = corexListsChild(corexListsSwitches(['hideTemplates' => true]));
    $this->roots[] = $child['root'];
    corexListsRegisterThemePatterns();
    $before = corexListsRegistered();

    Boot::app()->container()->make(ParentPatternsHider::class)->hide();

    expect(corexListsRegistered())->toBe($before)
        ->and(array_intersect(corexListsCorexPatternSlugs(), $before))->toHaveCount(26);
});

it('leaves everything as it was under the CoreX theme itself (User Story 5.4)', function () {
    switch_theme('corex');
    WP_Theme_JSON_Resolver::clean_cached_data();
    corexListsRegisterThemePatterns();
    $before = corexListsRegistered();

    Boot::app()->container()->make(ParentPatternsHider::class)->hide();

    expect(corexListsRegistered())->toBe($before)
        ->and(wp_get_theme()->get_page_templates(null, 'page'))->toHaveKey('page-landing');
});

it('lists no template or part the child lacks in the Site Editor, and still draws a page with one (FR-022, SC-006)', function () {
    $child = corexListsChild(corexListsSwitches(['hideTemplates' => true]));
    $this->roots[] = $child['root'];
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    expect($admins)->not->toBe([]);
    wp_set_current_user((int) $admins[0]);

    $templates = corexListsRest('/wp/v2/templates');
    $parts = corexListsRest('/wp/v2/template-parts');

    expect($templates)->toContain('front-page')
        ->and($templates)->not->toContain('index')
        ->and($templates)->not->toContain('page-landing')
        ->and($parts)->not->toContain('header')
        ->and($parts)->not->toContain('footer');

    // Outside the two routes the lists are whole: WordPress resolves a page through them.
    expect(array_column(get_block_templates(), 'slug'))->toContain('index')
        ->and(array_column(get_block_templates([], 'wp_template_part'), 'slug'))->toContain('header');

    // The CoreX theme's index, as WordPress serves it (it names the active theme on each part).
    $index = resolve_block_template('index', ['index'], '');
    expect($index)->toBeInstanceOf(WP_Block_Template::class)
        ->and($index->slug)->toBe('index')
        ->and(str_replace(',"theme":"' . $child['stylesheet'] . '"', '', $index->content))
        ->toBe((string) file_get_contents(get_template_directory() . '/templates/index.html'));
});

it('takes the CoreX theme\'s custom templates out of the page template choices, but not a page\'s own (FR-022)', function () {
    $child = corexListsChild(
        [...corexListsSwitches(['hideTemplates' => true]), 'customTemplates' => [['name' => 'page-acme', 'title' => 'Acme page', 'postTypes' => ['page']]]],
        ['templates/page-acme.html' => "<!-- wp:post-content /-->\n"],
    );
    $this->roots[] = $child['root'];

    $choices = wp_get_theme()->get_page_templates(null, 'page');

    // A page already drawn by Landing page keeps it among its choices, and saving it keeps it.
    $page = wp_insert_post(['post_type' => 'page', 'post_status' => 'draft', 'post_title' => 'Lists page']);
    expect($page)->toBeInt()->toBeGreaterThan(0);
    $this->created[] = $page;
    update_post_meta($page, '_wp_page_template', 'page-landing');
    wp_update_post(['ID' => $page, 'post_title' => 'Lists page, saved']);

    expect($choices)->toHaveKey('page-acme')
        ->and(array_intersect_key($choices, array_flip(['page-landing', 'page-contact', 'page-form', 'page-about', 'page-services', 'page-account'])))->toBe([])
        ->and(wp_get_theme()->get_page_templates(get_post($page)))->toHaveKey('page-landing')
        ->and(get_page_template_slug($page))->toBe('page-landing');
});

it('offers CoreX\'s six custom templates with their titles beside the child\'s own (FR-023, User Story 5.3)', function () {
    $child = corexListsChild(
        ['customTemplates' => [['name' => 'page-acme', 'title' => 'Acme page']]],
        ['templates/page-acme.html' => "<!-- wp:post-content /-->\n"],
    );
    $this->roots[] = $child['root'];

    $choices = wp_get_theme()->get_page_templates(null, 'page');

    expect(array_intersect_key($choices, array_flip(['page-landing', 'page-contact', 'page-form', 'page-about', 'page-services', 'page-account', 'page-acme'])))->toEqual([
        'page-landing' => 'Landing page',
        'page-contact' => 'Contact page',
        'page-form' => 'Form page',
        'page-about' => 'About page',
        'page-services' => 'Services page',
        'page-account' => 'Account page',
        'page-acme' => 'Acme page',
    ]);

    // What WordPress merged: CoreX's six as CoreX lists them, then the child's own.
    expect(WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => false])->get_custom_templates())->toBe([
        'page-landing' => ['title' => 'Landing page', 'postTypes' => ['page']],
        'page-contact' => ['title' => 'Contact page', 'postTypes' => ['page']],
        'page-form' => ['title' => 'Form page', 'postTypes' => ['page']],
        'page-about' => ['title' => 'About page', 'postTypes' => ['page']],
        'page-services' => ['title' => 'Services page', 'postTypes' => ['page']],
        'page-account' => ['title' => 'Account page', 'postTypes' => ['page']],
        'page-acme' => ['title' => 'Acme page', 'postTypes' => ['page']],
    ]);
});

it('keeps a child\'s own title for a CoreX custom template (FR-023)', function () {
    $child = corexListsChild(['customTemplates' => [['name' => 'page-contact', 'title' => 'Talk to Acme', 'postTypes' => ['page']]]]);
    $this->roots[] = $child['root'];

    $choices = wp_get_theme()->get_page_templates(null, 'page');

    expect($choices['page-contact'] ?? null)->toBe('Talk to Acme')
        ->and($choices['page-landing'] ?? null)->toBe('Landing page')
        ->and($choices['page-account'] ?? null)->toBe('Account page');
});

it('leaves a child that declares no custom templates inheriting CoreX\'s (FR-023)', function () {
    $child = corexListsChild(['settings' => []]);
    $this->roots[] = $child['root'];

    expect(array_column(WP_Theme_JSON_Resolver::get_theme_data([], ['with_supports' => false])->get_custom_templates(), 'title'))
        ->toBe(['Landing page', 'Contact page', 'Form page', 'About page', 'Services page', 'Account page']);
});

it('generates a client theme that sets neither switch, so a new site shows everything (FR-024)', function () {
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
        ->and($readme)->toContain('settings.custom.corex.parent.hidePatterns');
});
