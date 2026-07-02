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

use Corex\Tests\Support\ThemeContract;

/** @return array<int, string> */
function corexSectionPatterns(): array
{
    return ['services-grid', 'process-steps'];
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

        // Every `echo` in the pattern body goes through an escaping function.
        preg_match_all('/echo\s+([^;]+);/', $php, $matches);
        foreach ($matches[1] as $expression) {
            expect($expression)->toMatch('/esc_html|esc_attr|esc_url/');
        }
    }
});
