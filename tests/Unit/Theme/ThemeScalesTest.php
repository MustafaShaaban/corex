<?php

/**
 * The CoreX theme writes the design's scales and draws Arabic in the Arabic face (spec 121,
 * US-BRD-03, US-BRD-04, US-BRD-09; DECISIONS #462, #463, #464).
 *
 * What WordPress renders from these values is read in the browser by
 * tests/e2e/tokens-and-type.spec.js; here, what the theme asks for.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;
use Corex\Theme\ThemeDefaultPresets;

/** @return array<string,mixed> */
function theme_scales(): array
{
    return ThemeContract::json('theme/theme.json');
}

/** A rem length in pixels. */
function theme_scales_pixels(string $rem): float
{
    return (float) $rem * 16;
}

it('turns WordPress\'s own spacing, font sizes and shadows off', function () {
    $settings = theme_scales()['settings'];

    expect($settings['spacing']['defaultSpacingSizes'])->toBeFalse()
        ->and($settings['typography']['defaultFontSizes'])->toBeFalse()
        ->and($settings['shadow']['defaultPresets'])->toBeFalse();
});

it('writes the design\'s spacing scale on slugs that keep their names', function () {
    $sizes = theme_scales()['settings']['spacing']['spacingSizes'];

    expect(array_column($sizes, 'slug'))->toBe(['10', '20', '25', '30', '40', '50', '60', '70', '80'])
        ->and(array_map('theme_scales_pixels', array_column($sizes, 'size')))->toBe([4.0, 8.0, 12.0, 16.0, 24.0, 32.0, 48.0, 64.0, 96.0]);
});

it('names the font sizes by the design\'s roles at the design\'s sizes', function () {
    $sizes = theme_scales()['settings']['typography']['fontSizes'];
    $byName = array_column($sizes, 'size', 'name');

    expect($byName)->toMatchArray([
        'Caption' => '0.75rem',
        'Small' => '0.875rem',
        'Body' => '1rem',
        'Lead' => '1.375rem',
        'Title' => '1.75rem',
        'Heading 3' => '2.25rem',
        'Heading 2' => '3rem',
        'Heading 1' => '4rem',
        'Display' => '5.5rem',
    ])->and(array_column($sizes, 'slug'))->toContain('xs', 'sm', 'base', 'lg', 'xl', '2xl', 'hero', 'small', 'medium');
});

it('sets h1 to h6 on the type scale, at weight 600 with negative tracking', function () {
    $theme = theme_scales();
    $elements = $theme['styles']['elements'];
    $tracking = $theme['settings']['custom']['tracking'];
    $expected = [
        'h1' => ['hero', '1.05'], 'h2' => ['3-xl', '1.15'], 'h3' => ['2-xl', '1.2'],
        'h4' => ['xl', '1.3'], 'h5' => ['lg', '1.4'], 'h6' => ['base', '1.5'],
    ];

    expect($elements['heading']['typography']['fontWeight'])->toBe('600');

    foreach ($expected as $element => [$size, $lineHeight]) {
        $typography = $elements[$element]['typography'];
        preg_match('/--tracking--([a-z]+)\)$/', $typography['letterSpacing'], $match);

        expect($typography['fontSize'])->toBe("var(--wp--preset--font-size--{$size})")
            ->and($typography['lineHeight'])->toBe($lineHeight)
            ->and((float) $tracking[$match[1] ?? ''])->toBeLessThan(0.0);
    }
});

it('writes the design\'s radius and motion', function () {
    $custom = theme_scales()['settings']['custom'];

    expect($custom['radius'])->toBe(['xs' => '0.1875rem', 'sm' => '0.375rem', 'md' => '0.625rem', 'lg' => '1rem', 'full' => '999px'])
        ->and($custom['motion']['duration'])->toBe(['instant' => '80ms', 'fast' => '140ms', 'base' => '200ms', 'slow' => '320ms'])
        ->and(theme_scales()['styles']['elements']['button']['border']['radius'])->toBe('var(--wp--custom--radius--sm)');
});

it('sets buttons, navigation and form labels in the heading face', function () {
    $styles = theme_scales()['styles'];

    expect($styles['elements']['button']['typography']['fontFamily'])->toBe('var(--wp--preset--font-family--heading)')
        ->and($styles['blocks']['core/navigation']['typography']['fontFamily'])->toBe('var(--wp--preset--font-family--heading)')
        ->and($styles['css'])->toContain(':where(label, legend){font-family:var(--wp--preset--font-family--heading);}')
        ->and($styles['typography']['fontFamily'])->toBe('var(--wp--preset--font-family--body)');
});

it('draws a page in an Arabic-script language in the Arabic face at Arabic line heights', function () {
    $css = theme_scales()['styles']['css'];

    expect($css)->toContain(':lang(ar)')->toContain(':lang(fa)')
        ->toContain('body{font-family:var(--wp--preset--font-family--arabic);line-height:1.8;}')
        ->toContain('line-height:1.3;');
});

it('declares the Arabic character range on every Arabic face, so an English page loads none', function () {
    foreach (['theme/theme.json', 'theme/styles/dark.json', 'theme/styles/editorial.json'] as $file) {
        $families = array_column(ThemeContract::json($file)['settings']['typography']['fontFamilies'], null, 'slug');

        foreach ($families['arabic']['fontFace'] as $face) {
            expect($face['unicodeRange'] ?? '')->toStartWith('U+0600-06FF');
        }
    }
});

it('removes WordPress\'s default font sizes and shadows and keeps the rest of its defaults', function () {
    $defaults = [
        'settings' => [
            'typography' => ['fontSizes' => ['default' => []], 'fontFamilies' => ['default' => []]],
            'shadow' => ['presets' => ['default' => []], 'defaultPresets' => true],
            'color' => ['palette' => ['default' => []]],
        ],
    ];

    expect(ThemeDefaultPresets::without($defaults))->toBe([
        'settings' => [
            'typography' => ['fontFamilies' => ['default' => []]],
            'shadow' => ['defaultPresets' => true],
            'color' => ['palette' => ['default' => []]],
        ],
    ]);
});
