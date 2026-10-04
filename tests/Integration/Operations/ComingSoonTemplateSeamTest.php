<?php

/**
 * The WordPress behaviour spec 101's design rests on, proved before anything is built on it
 * (tasks T008–T010).
 *
 * The plan has `corex-config` register a block template named `coming-soon`, a client theme replace
 * it by shipping a template of the same name, and a self-contained page stand in when a theme has
 * no block templates at all. Each of those is a statement about WordPress, not about CoreX, so
 * these tests exercise WordPress and no CoreX code. If one of them fails after a core update, the
 * coming-soon page's design needs revisiting, and this is where that is found out.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

const COREX_SEAM_TEMPLATE = 'corex-seam-test//coming-soon';
const COREX_SEAM_DEFAULT  = 'PLUGIN DEFAULT PAGE';

function corexSeamThemesDirectory(): string
{
    return dirname(__DIR__, 2) . '/Fixtures/Themes';
}

/**
 * Resolve the `coming-soon` template the way a request would: by slug, with no PHP template to
 * fall back on.
 *
 * @return array{canvas:string, content:string, id:string}
 */
function corexSeamResolve(): array
{
    global $_wp_current_template_content, $_wp_current_template_id;

    $_wp_current_template_content = null;
    $_wp_current_template_id      = null;

    $canvas = locate_block_template('', 'coming-soon', ['coming-soon.php']);

    return [
        'canvas'  => (string) $canvas,
        'content' => (string) $_wp_current_template_content,
        'id'      => (string) $_wp_current_template_id,
    ];
}

function corexSeamActivate(string $stylesheet): void
{
    switch_theme($stylesheet);

    // WordPress decides block-template support once, while the theme is being set up. Switching
    // theme in the middle of a request does not redo that, so it is redone here the way core does
    // it: a theme is given the support if and only if it is a block theme.
    if (wp_is_block_theme()) {
        add_theme_support('block-templates');
    } else {
        remove_theme_support('block-templates');
    }

    // Registered after the theme is active, which is the order a real request has: a plugin
    // registers on `init`, long after the theme is chosen. The order is observable — WordPress
    // fixes a registered template's id when it is registered, from the theme active at that moment.
    register_block_template(COREX_SEAM_TEMPLATE, [
        'title'   => 'Coming soon (seam test)',
        'content' => '<!-- wp:paragraph --><p>' . COREX_SEAM_DEFAULT . '</p><!-- /wp:paragraph -->',
    ]);
}

beforeEach(function () {
    $this->previousStylesheet = get_stylesheet();
    $this->hadSupport         = current_theme_supports('block-templates');

    register_theme_directory(corexSeamThemesDirectory());
    wp_clean_themes_cache();
});

afterEach(function () {
    global $wp_theme_directories, $_wp_current_template_content, $_wp_current_template_id;

    if (WP_Block_Templates_Registry::get_instance()->is_registered(COREX_SEAM_TEMPLATE)) {
        unregister_block_template(COREX_SEAM_TEMPLATE);
    }
    switch_theme($this->previousStylesheet);

    if ($this->hadSupport) {
        add_theme_support('block-templates');
    } else {
        remove_theme_support('block-templates');
    }

    $wp_theme_directories = array_values(array_filter(
        (array) $wp_theme_directories,
        static fn (string $directory): bool => wp_normalize_path($directory) !== wp_normalize_path(corexSeamThemesDirectory()),
    ));
    wp_clean_themes_cache();

    $_wp_current_template_content = null;
    $_wp_current_template_id      = null;
});

it('finds a plugin-registered template under a block theme that has none of its own', function () {
    corexSeamActivate('corex-seam-plain');

    $resolved = corexSeamResolve();

    expect($resolved['canvas'])->toEndWith('template-canvas.php')
        ->and($resolved['content'])->toContain(COREX_SEAM_DEFAULT)
        // A registered template is addressed under the active theme, which is what lets the Site
        // Editor save a customised copy of it.
        ->and($resolved['id'])->toBe('corex-seam-plain//coming-soon');
});

it('lets a theme replace the registered template by shipping one with the same name', function () {
    corexSeamActivate('corex-seam-own-page');

    $resolved = corexSeamResolve();

    expect($resolved['canvas'])->toEndWith('template-canvas.php')
        ->and($resolved['content'])->toContain('THEME OWN PAGE')
        ->and($resolved['content'])->not->toContain(COREX_SEAM_DEFAULT);
});

it('resolves nothing under a theme with no block-template support, registered template or not', function () {
    corexSeamActivate('corex-seam-classic');

    $resolved = corexSeamResolve();

    // The case the self-contained fallback page exists for (FR-009).
    expect($resolved['canvas'])->toBe('')
        ->and($resolved['content'])->toBe('');
});

it('puts the active theme back, so the tests above leave the install as they found it', function () {
    expect(get_stylesheet())->toBe($this->previousStylesheet);
});
