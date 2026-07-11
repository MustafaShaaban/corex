<?php

/**
 * Spec 068 US9 (FR-156/FR-157) — approved theme template & pattern coverage.
 *
 * Verifies the theme ships every approved page surface — Home, About, Services,
 * Contact, Landing, Blog, Single Post, Portfolio/Project, Search, No Results, 404,
 * Maintenance, Loading, Comments, Newsletter, and Footer — as a block template or a
 * registered pattern; that custom page templates are declared in theme.json; that
 * every template wires the header/footer parts; that list templates carry a truthful
 * query-no-results empty state; and that templates embed no PHP business logic
 * (block-theme templates are static HTML by construction).
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

function corexTemplate(string $name): string
{
    return (string) file_get_contents(ThemeContract::root() . "/theme/templates/{$name}.html");
}

function corexPattern(string $name): string
{
    return (string) file_get_contents(ThemeContract::root() . "/theme/patterns/{$name}.php");
}

it('ships every approved core block template', function () {
    $required = [
        'front-page', 'index', 'single', 'archive', 'search', '404',
        'single-corex_project', 'archive-corex_project',
    ];

    foreach ($required as $template) {
        $file = ThemeContract::root() . "/theme/templates/{$template}.html";
        expect(is_file($file))->toBeTrue("template {$template} missing");
    }
});

it('ships and declares every approved custom page template', function () {
    $custom = ['page-landing', 'page-contact', 'page-form', 'page-about', 'page-services'];

    $themeJson = ThemeContract::json('theme/theme.json');
    $declared = array_column($themeJson['customTemplates'] ?? [], 'name');

    foreach ($custom as $template) {
        expect(is_file(ThemeContract::root() . "/theme/templates/{$template}.html"))
            ->toBeTrue("custom template {$template} missing");
        expect(in_array($template, $declared, true))
            ->toBeTrue("custom template {$template} not declared in theme.json");
    }
});

it('covers every approved page surface as a template or pattern', function () {
    // Maintenance/Loading are also live runtime surfaces (Corex\Admin\StandalonePage
    // and block skeletons); the theme additionally ships editor patterns for them.
    $patternSurfaces = [
        'section-newsletter',   // Newsletter
        'maintenance',          // Maintenance
        'loading',              // Loading
    ];

    foreach ($patternSurfaces as $pattern) {
        expect(is_file(ThemeContract::root() . "/theme/patterns/{$pattern}.php"))
            ->toBeTrue("pattern {$pattern} missing");
        expect(corexPattern($pattern))->toMatch('/Slug:\s*corex\/' . $pattern . '/');
    }
});

it('wires the header and footer parts into every full-page template', function () {
    $templates = ['front-page', 'index', 'single', 'archive', 'search', '404',
        'page-landing', 'page-contact', 'page-about', 'page-services',
        'single-corex_project', 'archive-corex_project'];

    foreach ($templates as $template) {
        $html = corexTemplate($template);
        expect($html)->toContain('"slug":"header"')
            ->and($html)->toContain('"slug":"footer"');
    }
});

it('gives list templates a truthful no-results empty state', function () {
    foreach (['index', 'search', 'archive'] as $template) {
        expect(corexTemplate($template))->toContain('wp:query-no-results');
    }
});

it('keeps comments on the single post template', function () {
    expect(corexTemplate('single'))->toContain('wp:comments');
});

it('embeds no PHP business logic in block templates', function () {
    foreach (glob(ThemeContract::root() . '/theme/templates/*.html') ?: [] as $file) {
        expect(str_contains((string) file_get_contents($file), '<?php'))
            ->toBeFalse(basename($file) . ' contains PHP');
    }
});
