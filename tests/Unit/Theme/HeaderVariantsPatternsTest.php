<?php

/**
 * Spec 058 US4 — header variant patterns.
 *
 * Verifies the five header variants ship, register under the CoreX + core header
 * categories, carry their variant class, and expose accessible action slots
 * (labelled language link, native search, mega menu). Sticky/transparent rendering
 * is browser-gated. Raw-literal freedom is covered by NavigationPatternsTest.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

/** @return array<int, string> */
function corexHeaderVariants(): array
{
    return ['corporate', 'saas', 'docs', 'transparent', 'minimal'];
}

it('ships every header variant pattern', function () {
    foreach (corexHeaderVariants() as $variant) {
        $file = ThemeContract::root() . "/theme/patterns/header-{$variant}.php";
        expect(is_file($file))->toBeTrue("header-{$variant} missing");
    }
});

it('registers each variant under the CoreX + core header categories with its class', function () {
    foreach (corexHeaderVariants() as $variant) {
        $php = (string) file_get_contents(ThemeContract::root() . "/theme/patterns/header-{$variant}.php");

        expect($php)->toMatch('/Slug:\s*corex\/header-' . $variant . '/')
            ->and($php)->toMatch('/Categories:[^\n]*corex/')
            ->and($php)->toMatch('/Categories:[^\n]*header/')
            ->and($php)->toContain('corex-header--' . $variant);
    }
});

it('exposes accessible, labelled action slots in the relevant variants', function () {
    $corporate = (string) file_get_contents(ThemeContract::root() . '/theme/patterns/header-corporate.php');
    $docs = (string) file_get_contents(ThemeContract::root() . '/theme/patterns/header-docs.php');
    $saas = (string) file_get_contents(ThemeContract::root() . '/theme/patterns/header-saas.php');

    // Language link carries an accessible name; docs uses the native search block;
    // the SaaS header offers a mega-menu disclosure.
    expect($corporate)->toContain('aria-label=')
        ->and($docs)->toContain('wp:search')
        ->and($saas)->toContain('corex-mega');
});

it('marks the transparent header for the sticky/solid scroll state', function () {
    $transparent = (string) file_get_contents(ThemeContract::root() . '/theme/patterns/header-transparent.php');

    expect($transparent)->toContain('corex-header--transparent');
});
