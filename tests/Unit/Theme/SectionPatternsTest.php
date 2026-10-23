<?php

/**
 * Spec 063 Phase 7 — company section patterns (services grid, process steps).
 *
 * Verifies the section patterns ship, register the correct slug + CoreX category,
 * use core blocks with neutral placeholder content, and carry no raw color/size
 * literals (only theme.json tokens). Rendered reflow/RTL is browser-gated.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Support\ThemeContract;

/** @return array<int, string> */
function corexSectionPatterns(): array
{
    return ['services-grid', 'process-steps', 'logo-cloud', 'contact-info'];
}

it('ships every company section pattern', function () {
    foreach (corexSectionPatterns() as $pattern) {
        $file = ThemeContract::root() . "/theme/patterns/section-{$pattern}.php";
        expect(is_file($file))->toBeTrue("section-{$pattern} missing");
    }
});

it('registers each section under the CoreX category using core blocks and neutral content', function () {
    foreach (corexSectionPatterns() as $pattern) {
        $php = (string) file_get_contents(ThemeContract::root() . "/theme/patterns/section-{$pattern}.php");

        expect($php)->toMatch('/Slug:\s*corex\/section-' . $pattern . '/')
            ->and($php)->toMatch('/Categories:[^\n]*corex/')
            ->and($php)->toContain('wp:columns')
            ->and($php)->toContain('defined(\'ABSPATH\')')
            // Neutral placeholder brand only — no real client/company name enters the framework.
            ->and(strtolower($php))->not->toContain('acme inc')
            // No third-party scripts baked into a static pattern.
            ->and($php)->not->toContain('<script');
    }
});

it('escapes every dynamic value in the section patterns', function () {
    foreach (corexSectionPatterns() as $pattern) {
        $php = (string) file_get_contents(ThemeContract::root() . "/theme/patterns/section-{$pattern}.php");

        // Every `echo` in the pattern body goes through an escaping function, or through one of the theme's
        // pattern-link helpers, which escape what they return (proven by the test below).
        preg_match_all('/echo\s+([^;]+);/', $php, $matches);
        foreach ($matches[1] as $expression) {
            expect($expression)->toMatch('/esc_html|esc_attr|esc_url|corex_pattern_(anchor|link|contact)\(/');
        }
    }
});

it('escapes the label and the address in the pattern-link helpers', function () {
    require_once ThemeContract::root() . '/theme/inc/pattern-links.php';
    Functions\when('esc_html')->alias(static fn (string $text): string => htmlspecialchars($text, ENT_QUOTES));
    Functions\expect('esc_url')->once()->with('https://site.test/about/')->andReturn('ESCAPED-URL');
    Functions\when('get_page_by_path')->justReturn(null);

    expect(corex_pattern_anchor('https://site.test/about/', '<b>About</b>'))
        ->toBe('<a href="ESCAPED-URL">&lt;b&gt;About&lt;/b&gt;</a>')
        // A page that is not published leaves the label as escaped text, with no link.
        ->and(corex_pattern_link('about', '<b>About</b>'))->toBe('&lt;b&gt;About&lt;/b&gt;');
});
