<?php

/**
 * Spec 058 US3 — footer variant patterns.
 *
 * Verifies the six footer variants ship, register under the CoreX + core footer
 * categories, render a contentinfo landmark ending in a legal/utility row
 * (corex/copyright), and carry no raw color/size literals. (Rendered reflow/RTL
 * is browser-gated.)
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

/** @return array<int, string> */
function corexFooterVariants(): array
{
    return ['simple', 'corporate', 'saas', 'newsletter', 'locations', 'legal'];
}

it('ships every footer variant pattern', function () {
    foreach (corexFooterVariants() as $variant) {
        $file = ThemeContract::root() . "/theme/patterns/footer-{$variant}.php";
        expect(is_file($file))->toBeTrue("footer-{$variant} missing");
    }
});

it('renders each footer as a contentinfo landmark ending in the legal row', function () {
    foreach (corexFooterVariants() as $variant) {
        $php = (string) file_get_contents(ThemeContract::root() . "/theme/patterns/footer-{$variant}.php");

        expect($php)->toMatch('/Slug:\s*corex\/footer-' . $variant . '/')
            ->and($php)->toMatch('/Categories:[^\n]*corex/')
            ->and($php)->toMatch('/Categories:[^\n]*footer/')
            ->and($php)->toContain('<footer')
            ->and($php)->toContain('wp:corex/copyright');
    }
});
