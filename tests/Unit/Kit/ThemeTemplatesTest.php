<?php

/**
 * Unit tests for the theme FSE templates (spec 010 US1+US2: FR-001, FR-002, FR-003, SC-001..3).
 *
 * Verifies template/part presence, token-only markup, and that the front page composes the
 * Corex section patterns. (Visual/editor correctness needs a browser — out of scope here.)
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

function themeDir(): string
{
    return dirname(__DIR__, 3) . '/theme';
}

it('ships the universal templates and parts', function () {
    foreach (['front-page', 'page', 'single', 'archive', 'search', '404', 'index'] as $template) {
        expect(is_file(themeDir() . "/templates/{$template}.html"))->toBeTrue("template {$template} missing");
    }

    foreach (['header', 'footer'] as $part) {
        expect(is_file(themeDir() . "/parts/{$part}.html"))->toBeTrue("part {$part} missing");
    }
});

it('uses no hardcoded colors or pixel sizes in any template or part', function () {
    $files = array_merge(
        glob(themeDir() . '/templates/*.html') ?: [],
        glob(themeDir() . '/parts/*.html') ?: [],
    );

    foreach ($files as $file) {
        $html = (string) file_get_contents($file);

        expect($html)->not->toMatch('/#[0-9a-fA-F]{3,6}\b/')   // no hex colors
            ->and($html)->not->toMatch('/:\s*\d+px\b/');        // no pixel sizes
    }
});

it('composes the Corex section patterns within the front page', function () {
    $front = (string) file_get_contents(themeDir() . '/templates/front-page.html');

    expect($front)->toContain('corex/hero')
        ->toContain('corex/contact')
        ->toContain('"slug":"header"')
        ->toContain('"slug":"footer"');
});
