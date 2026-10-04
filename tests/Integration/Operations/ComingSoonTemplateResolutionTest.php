<?php

/**
 * Which coming-soon page a site actually serves, under each kind of theme (spec 101, FR-008 and
 * FR-009; User Story 6).
 *
 * ComingSoonTemplateSeamTest proves WordPress behaves the way the design assumes, with a stand-in
 * template. This is the same three themes with CoreX's real default registered, resolved through
 * the class the guard uses — so it is the feature's behaviour, not the seam's.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Operations\ComingSoonTemplate;

function corexComingSoonTemplateService(): ComingSoonTemplate
{
    return Boot::app()->container()->make(ComingSoonTemplate::class);
}

/**
 * Switch to a fixture theme and register CoreX's default the way a request on that theme would
 * have: after the theme is chosen.
 */
function corexComingSoonUnderTheme(string $stylesheet): void
{
    switch_theme($stylesheet);

    // WordPress decides block-template support once, during theme setup; a switch mid-request
    // does not redo it, so it is redone here by core's own rule.
    if (wp_is_block_theme()) {
        add_theme_support('block-templates');
    } else {
        remove_theme_support('block-templates');
    }

    if (WP_Block_Templates_Registry::get_instance()->is_registered(ComingSoonTemplate::NAME)) {
        unregister_block_template(ComingSoonTemplate::NAME);
    }
    corexComingSoonTemplateService()->registerDefault();
}

beforeEach(function () {
    $this->previousStylesheet = get_stylesheet();
    $this->hadSupport         = current_theme_supports('block-templates');
    $this->fixtures           = dirname(__DIR__, 2) . '/Fixtures/Themes';

    register_theme_directory($this->fixtures);
    wp_clean_themes_cache();
});

afterEach(function () {
    global $wp_theme_directories, $_wp_current_template_content, $_wp_current_template_id;

    switch_theme($this->previousStylesheet);
    if ($this->hadSupport) {
        add_theme_support('block-templates');
    } else {
        remove_theme_support('block-templates');
    }

    $fixtures             = wp_normalize_path($this->fixtures);
    $wp_theme_directories = array_values(array_filter(
        (array) $wp_theme_directories,
        static fn (string $directory): bool => wp_normalize_path($directory) !== $fixtures,
    ));
    wp_clean_themes_cache();

    // Put the default back under the theme the install really runs.
    if (WP_Block_Templates_Registry::get_instance()->is_registered(ComingSoonTemplate::NAME)) {
        unregister_block_template(ComingSoonTemplate::NAME);
    }
    corexComingSoonTemplateService()->registerDefault();

    $_wp_current_template_content = null;
    $_wp_current_template_id      = null;
});

it('serves CoreX\'s default under a block theme unrelated to the Corex theme', function () {
    global $_wp_current_template_content;
    corexComingSoonUnderTheme('corex-seam-plain');

    $canvas = corexComingSoonTemplateService()->canvas();

    // User Story 6.2: a generated client theme is a standalone block theme and inherits nothing
    // from the Corex theme. The default reaches it anyway, because the plugin supplies it.
    expect(wp_get_theme()->parent())->toBeFalse()
        ->and($canvas)->toEndWith('template-canvas.php')
        ->and((string) $_wp_current_template_content)->toContain('Coming soon')
        ->and((string) $_wp_current_template_content)->toContain('<!-- wp:site-title');
});

it('serves the theme\'s own page in place of the default when the theme ships one', function () {
    global $_wp_current_template_content;
    corexComingSoonUnderTheme('corex-seam-own-page');

    $canvas = corexComingSoonTemplateService()->canvas();

    expect($canvas)->toEndWith('template-canvas.php')
        ->and((string) $_wp_current_template_content)->toContain('THEME OWN PAGE')
        ->and((string) $_wp_current_template_content)->not->toContain('We are getting things ready');
});

it('has no template to serve under a theme with no block templates, and a whole page instead', function () {
    corexComingSoonUnderTheme('corex-seam-classic');

    $template = corexComingSoonTemplateService();

    // FR-009: the guard sends this document, with a 200, when the canvas is empty.
    expect($template->canvas())->toBe('')
        ->and($template->standalone())->toStartWith('<!DOCTYPE html>')
        ->and($template->standalone())->toContain('Coming soon')
        ->and($template->standalone())->toContain(get_bloginfo('name'));
});
