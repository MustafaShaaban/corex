<?php

/**
 * The breakpoint writer on a real WordPress (spec 137, slice 1; US-NAV-11; FR-002, FR-004, FR-005,
 * FR-007 (d); DECISIONS #692).
 *
 * What is measured is WordPress's own: the theme JSON merge that gives a child's token, the style
 * registry the rules go into, and the Navigation block stylesheet whose 600px rules the CoreX
 * header overrides. The child is the browser suite's fixture, `tests/e2e/fixtures/themes`, so the
 * two suites prove the same theme.
 *
 * @package Corex\Tests\Integration\Theme
 */

declare(strict_types=1);

use Corex\Container\Container;
use Corex\Theme\NavigationCollapseStyle;

/** The two rules of WordPress's Navigation block stylesheet the CoreX header's override answers. */
const COREX_WP_NAV_RULES = [
    '.wp-block-navigation__responsive-container:not(.hidden-by-default):not(.is-menu-open)' => 'display:block',
    '.wp-block-navigation__responsive-container-open:not(.always-shown)' => 'display:none',
];

/** CSS without comments and without the spaces a minifier takes out. */
function corexCollapseCompact(string $css): string
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', $css);
    $css = (string) preg_replace('/\s+/', ' ', $css);

    return (string) preg_replace('/\s*([{}:;,()])\s*/', '$1', $css);
}

/**
 * The declarations of every rule with this selector inside a media rule with this condition.
 *
 * @return list<string>
 */
function corexCollapseDeclarations(string $compactCss, string $condition, string $selector): array
{
    $found = [];
    $offset = 0;
    $opening = '@media' . $condition . '{';

    while (($at = strpos($compactCss, $opening, $offset)) !== false) {
        $start = $at + strlen($opening);
        $depth = 1;
        $end = $start;
        while ($depth > 0 && $end < strlen($compactCss)) {
            $depth += $compactCss[$end] === '{' ? 1 : ($compactCss[$end] === '}' ? -1 : 0);
            $end++;
        }
        $body = substr($compactCss, $start, $end - $start - 1);

        preg_match_all('/(?:^|(?<=}))([^{}]+)\{([^}]*)\}/', $body, $rules, PREG_SET_ORDER);
        foreach ($rules as $rule) {
            if (in_array($selector, array_map('trim', explode(',', $rule[1])), true)) {
                $found[] = $rule[2];
            }
        }

        $offset = $start;
    }

    return $found;
}

function corexCollapseService(): NavigationCollapseStyle
{
    // The provider's own binding, so what is measured is what boots on a site.
    $container = new Container();
    (new Corex\Theme\NavigationServiceProvider($container))->register();

    return $container->make(NavigationCollapseStyle::class);
}

/** What the writer leaves in WordPress's style registry: the source, and the rules printed inline. */
function corexCollapseRegistered(): array
{
    $styles = wp_styles();
    $style = $styles->registered[NavigationCollapseStyle::HANDLE] ?? null;

    return [
        'src' => $style?->src,
        'inline' => implode("\n", (array) $styles->get_data(NavigationCollapseStyle::HANDLE, 'after')),
        'enqueued' => in_array(NavigationCollapseStyle::HANDLE, $styles->queue, true),
    ];
}

beforeEach(function () {
    $this->previousStylesheet = get_stylesheet();
    wp_deregister_style(NavigationCollapseStyle::HANDLE);
});

afterEach(function () {
    global $wp_theme_directories;

    wp_dequeue_style(NavigationCollapseStyle::HANDLE);
    wp_deregister_style(NavigationCollapseStyle::HANDLE);

    if (get_stylesheet() !== $this->previousStylesheet) {
        switch_theme($this->previousStylesheet);
        delete_option('theme_mods_corex-e2e-nav-child');
    }

    $fixtures = wp_normalize_path(dirname(__DIR__, 3) . '/tests/e2e/fixtures/themes');
    $wp_theme_directories = array_values(array_filter(
        (array) $wp_theme_directories,
        static fn (string $directory): bool => wp_normalize_path($directory) !== $fixtures,
    ));
    wp_clean_themes_cache();
    WP_Theme_JSON_Resolver::clean_cached_data();
    wp_clean_theme_json_cache();
});

it('still writes, at 600px, the two rules of the Navigation block that the CoreX header overrides (FR-007 d)', function () {
    // Every copy: a site serves the minified one unless SCRIPT_DEBUG is on, and a right-to-left
    // page the `-rtl` one.
    foreach (['style.css', 'style.min.css', 'style-rtl.css', 'style-rtl.min.css'] as $file) {
        $path = ABSPATH . WPINC . '/blocks/navigation/' . $file;
        expect($path)->toBeFile();
        $css = corexCollapseCompact((string) file_get_contents($path));

        foreach (COREX_WP_NAV_RULES as $selector => $declaration) {
            $declarations = corexCollapseDeclarations($css, '(min-width:600px)', $selector);

            expect(str_contains(implode(';', $declarations), $declaration))->toBeTrue(
                "WordPress's {$file} no longer writes `{$selector} { {$declaration} }` from 600px up:"
                . ' the CoreX header\'s override answers that rule (spec 137, FR-002).',
            );
        }
    }
});

it('overrides those two rules, inside the CoreX header, between 600px and the token (FR-002)', function () {
    $css = corexCollapseCompact((string) file_get_contents(get_template_directory() . '/' . NavigationCollapseStyle::RELATIVE_PATH));

    foreach (COREX_WP_NAV_RULES as $selector => $declaration) {
        $answer = $declaration === 'display:block' ? 'display:none' : 'display:flex';

        expect(implode(';', corexCollapseDeclarations($css, '(600px <= width < 782px)', '.corex-header ' . $selector)))
            ->toContain($answer);
    }
});

it('serves the file under the CoreX theme, whose token is the default (FR-001, FR-004)', function () {
    switch_theme('corex');
    WP_Theme_JSON_Resolver::clean_cached_data();
    wp_clean_theme_json_cache();

    expect(wp_get_global_settings(['custom', 'nav', 'breakpoint']))->toBe('782px');

    corexCollapseService()->enqueue();

    $registered = corexCollapseRegistered();
    expect($registered['src'])->toBe(get_template_directory_uri() . '/' . NavigationCollapseStyle::RELATIVE_PATH)
        ->and($registered['inline'])->toBe('')
        ->and($registered['enqueued'])->toBeTrue();
});

it('prints the rules at a child\'s own width, with no file of the CoreX theme copied (FR-005, SC-001)', function () {
    $root = dirname(__DIR__, 3) . '/tests/e2e/fixtures/themes';
    expect(register_theme_directory($root))->toBeTrue();
    wp_clean_themes_cache();
    switch_theme('corex-e2e-nav-child');
    WP_Theme_JSON_Resolver::clean_cached_data();
    wp_clean_theme_json_cache();

    expect(get_stylesheet())->toBe('corex-e2e-nav-child')
        ->and(get_template())->toBe('corex')
        ->and(is_file(get_stylesheet_directory() . '/' . NavigationCollapseStyle::RELATIVE_PATH))->toBeFalse()
        ->and(wp_get_global_settings(['custom', 'nav', 'breakpoint']))->toBe('1024px');

    corexCollapseService()->enqueue();

    $registered = corexCollapseRegistered();
    expect($registered['src'])->toBeFalse()
        ->and($registered['inline'])->toContain('@media (width >= 1024px)')
        ->and($registered['inline'])->toContain('@media (600px <= width < 1024px)')
        ->and($registered['inline'])->not->toContain('782px')
        ->and($registered['enqueued'])->toBeTrue();
});
