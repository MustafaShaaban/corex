<?php

/**
 * Unit tests for the default coming-soon page and its self-contained fallback (spec 101, T019,
 * T020, T023). Headless: what is checked here is the markup CoreX ships. That WordPress finds the
 * registered template, and that a theme's own replaces it, is proved against a real install in
 * ComingSoonTemplateSeamTest and ComingSoonModeTest.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Admin\StandalonePage;
use Corex\Config\Operations\ComingSoonTemplate;

function comingSoonTemplate(): ComingSoonTemplate
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();

    $assets = dirname(__DIR__, 3) . '/plugins/corex-core/assets';

    return new ComingSoonTemplate(new StandalonePage($assets, 'http://example.test/plugins/corex-core/assets'));
}

/**
 * @return list<string> The name of every block in the markup, in order.
 */
function comingSoonBlockNames(string $markup): array
{
    preg_match_all('/<!-- wp:([a-z0-9\/-]+)/', $markup, $found);

    return $found[1];
}

it('builds the default page from core blocks only', function () {
    $names = comingSoonBlockNames(comingSoonTemplate()->defaultContent());

    // Core blocks carry no namespace. A CoreX block here would make the default depend on
    // corex-blocks being active, and the mode has to work without it (Principle II).
    expect($names)->not->toBeEmpty()
        ->and(array_filter($names, static fn (string $name): bool => str_contains($name, '/')))->toBe([]);
});

it('gives the default page no header or footer part', function () {
    // A site that is not open has no navigation to offer: every link in a header would be
    // redirected straight back to this page.
    expect(comingSoonTemplate()->defaultContent())->not->toContain('wp:template-part');
});

it('closes every block the default page opens', function () {
    $content = comingSoonTemplate()->defaultContent();

    preg_match_all('/<!-- wp:[a-z0-9\/-]+ (?:\{.*?\} )?-->/s', $content, $opened);
    preg_match_all('/<!-- \/wp:[a-z0-9\/-]+ -->/', $content, $closed);

    expect(count($opened[0]))->toBeGreaterThan(0)
        ->and(count($opened[0]))->toBe(count($closed[0]));
});

it('says the page is coming soon, in a string a translator can reach', function () {
    $seen = [];
    Functions\when('esc_html__')->alias(static function (string $text, string $domain) use (&$seen): string {
        $seen[] = [$text, $domain];

        return 'ترجمة';
    });
    $template = new ComingSoonTemplate(new StandalonePage('', ''));

    $content = $template->defaultContent();

    expect($content)->toContain('ترجمة')
        ->and($content)->not->toContain('Coming soon')
        ->and(array_column($seen, 0))->toContain('Coming soon')
        ->and(array_unique(array_column($seen, 1)))->toBe(['corex']);
});

it('uses no raw colour or size the theme did not provide', function () {
    $content = comingSoonTemplate()->defaultContent();

    // Colours come from the active theme's own styles; spacing from the presets every block theme
    // has. A hex value here would be CoreX's brand on a client's launch page (Principle V).
    expect($content)->not->toMatch('/#[0-9a-fA-F]{3,8}\b/')
        ->and($content)->not->toMatch('/\b\d+px\b/')
        ->and($content)->not->toContain('rgb(');
});

it('names the registered template so a theme file called coming-soon replaces it', function () {
    expect(ComingSoonTemplate::SLUG)->toBe('coming-soon')
        ->and(ComingSoonTemplate::NAME)->toBe('corex-config//coming-soon');
});

it('builds a self-contained fallback page that carries the site name and no CoreX mark', function () {
    Functions\when('get_bloginfo')->alias(static fn (string $show = ''): string => $show === 'name' ? 'Acme' : '');
    Functions\when('get_option')->justReturn('1');

    $html = comingSoonTemplate()->standalone();

    expect($html)->toStartWith('<!DOCTYPE html>')
        ->and($html)->toContain('<title>Acme</title>')
        ->and($html)->toContain('Coming soon')
        ->and($html)->toContain('corex-standalone--coming-soon')
        // The interstitials carry CoreX's mark because they are CoreX speaking to an operator.
        // This is the client's public page; it says who the client is and nothing about CoreX.
        // (The class name alone is in the inlined stylesheet; the element is what must be absent.)
        ->and($html)->not->toContain('class="corex-standalone__mark"')
        ->and($html)->not->toContain('<svg');
});

it('leaves the fallback page indexable while WordPress is not discouraging search engines', function () {
    Functions\when('get_bloginfo')->justReturn('');
    Functions\when('get_option')->alias(static fn (string $key): string => $key === 'blog_public' ? '1' : '');

    expect(comingSoonTemplate()->standalone())->not->toContain('noindex');
});

it('asks crawlers to stay away from the fallback page when WordPress is set to discourage them', function () {
    // The fallback is a whole document and never runs wp_head, so WordPress's own setting would
    // not reach it. It is read here instead, so the one control an operator has still works.
    Functions\when('get_bloginfo')->justReturn('');
    Functions\when('get_option')->alias(static fn (string $key): string => $key === 'blog_public' ? '0' : '');

    expect(comingSoonTemplate()->standalone())->toContain('<meta name="robots" content="noindex, nofollow"');
});
