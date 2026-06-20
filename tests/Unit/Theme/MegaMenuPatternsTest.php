<?php

/**
 * Spec 058 US2 — mega-menu patterns.
 *
 * Verifies the four mega-menu variants ship, register under the CoreX category, and
 * build on the native details/summary disclosure (so they are usable with no JS).
 * Raw-literal freedom is covered by NavigationPatternsTest's patterns/*.php scan.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

/** @return array<int, string> */
function corexMegaVariants(): array
{
    return ['simple', 'services', 'product', 'docs'];
}

it('ships every mega-menu variant pattern', function () {
    foreach (corexMegaVariants() as $variant) {
        $file = ThemeContract::root() . "/theme/patterns/megamenu-{$variant}.php";
        expect(is_file($file))->toBeTrue("megamenu-{$variant} missing");
    }
});

it('builds each mega menu on a native details disclosure under the CoreX category', function () {
    foreach (corexMegaVariants() as $variant) {
        $php = (string) file_get_contents(ThemeContract::root() . "/theme/patterns/megamenu-{$variant}.php");

        expect($php)->toMatch('/Slug:\s*corex\/megamenu-' . $variant . '/')
            ->and($php)->toMatch('/Categories:[^\n]*corex/')
            ->and($php)->toContain('wp:details')
            ->and($php)->toContain('corex-mega')
            ->and($php)->toContain('<summary>');
    }
});
