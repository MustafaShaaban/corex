<?php

/**
 * The base elements the CoreX theme draws (spec 134, FR-011 and FR-012), from the approved visitor
 * kit (`design/handoffs/visitor-kit.md`): the brass button with a graphite label, the link, the focus
 * ring and selected text. What the theme asks for is read here; what the browser draws from it is
 * measured by `tests/e2e/section-bands.spec.js`.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

/** @return array<string,mixed> */
function base_elements(string $file = 'theme/theme.json'): array
{
    return ThemeContract::json($file);
}

/** The theme's own CSS (`styles.css`) with its whitespace as written. */
function base_elements_css(): string
{
    return (string) base_elements()['styles']['css'];
}

it('draws the button in brass with the on-accent label, brass-hover on hover, in the control corner (FR-011)', function () {
    $button = base_elements()['styles']['elements']['button'];

    expect($button['color'])->toBe([
        'background' => 'var(--wp--preset--color--accent)',
        'text'       => 'var(--wp--custom--color--on-accent)',
    ])
        ->and($button[':hover']['color'])->toBe([
            'background' => 'var(--wp--custom--color--accent-hover)',
            'text'       => 'var(--wp--custom--color--on-accent)',
        ])
        ->and($button['border']['radius'])->toBe('var(--wp--custom--radius--md)')
        ->and(base_elements()['settings']['custom']['radius']['md'])->toBe('0.625rem');
});

/*
 * The kit's button (bundle.css 6.1): 48px tall, no block padding, 24px across, a 1px transparent edge so
 * every style is one height, line height 1.2, its label centred in an inline flex box. 48px is the
 * theme's `custom.control.height` (3rem).
 */
it('gives every button the kit\'s height, padding, edge and line from tokens (FR-011)', function () {
    $button = base_elements()['styles']['elements']['button'];
    $sizes = array_column(base_elements()['settings']['spacing']['spacingSizes'], 'size', 'slug');

    expect($button['spacing']['padding'])->toBe([
        'top'    => '0',
        'bottom' => '0',
        'left'   => 'var(--wp--preset--spacing--40)',
        'right'  => 'var(--wp--preset--spacing--40)',
    ])
        ->and($button['border'])->toBe([
            'radius' => 'var(--wp--custom--radius--md)',
            'width'  => 'var(--wp--custom--border--width--thin)',
            'style'  => 'solid',
            'color'  => 'transparent',
        ])
        ->and($button['typography'])->toMatchArray(['fontSize' => 'var(--wp--preset--font-size--base)', 'lineHeight' => '1.2'])
        ->and([$sizes['40'], base_elements()['settings']['custom']['control']['height']])->toBe(['1.5rem', '3rem'])
        ->and(base_elements_css())->toContain(
            ':root :where(.wp-element-button, .wp-block-button__link){display:inline-flex;align-items:center;justify-content:center;'
            . 'gap:var(--wp--preset--spacing--20);box-sizing:border-box;min-block-size:var(--wp--custom--control--height);}',
        );
});

it('draws a disabled button as a quiet surface that cannot be pressed (FR-011)', function () {
    expect(base_elements_css())->toContain(
        ':where(.wp-element-button, .wp-block-button__link):is(:disabled, [aria-disabled="true"]), '
        . ':where(.wp-element-button, .wp-block-button__link):is(:disabled, [aria-disabled="true"]):hover'
        . '{background-color:var(--wp--preset--color--surface-strong, var(--wp--preset--color--surface-alt));'
        . 'color:var(--wp--preset--color--ink-soft);cursor:not-allowed;}',
    )
        // A disabled link keeps its address; it takes no click.
        ->and(base_elements_css())->toContain(':where(.wp-element-button, .wp-block-button__link)[aria-disabled="true"]{pointer-events:none;}');
});

it('draws links in the darker brass, underlined, turning primary on hover (FR-012)', function () {
    $link = base_elements()['styles']['elements']['link'];

    expect($link['color']['text'])->toBe('var(--wp--preset--color--accent-dark)')
        ->and($link['typography']['textDecoration'])->toBe('underline')
        ->and($link[':hover']['color']['text'])->toBe('var(--wp--preset--color--primary)')
        // The kit's 1px line, the thin border token. Its 0.2em offset has no token and is not set.
        ->and(base_elements_css())->toContain('a:where(:not(.wp-element-button)){text-decoration-thickness:var(--wp--custom--border--width--thin);}');
});

it('rings a focused link, button, form button or field 2px in brass, 2px away (FR-012)', function () {
    $ring = [
        'color'  => 'var(--wp--custom--focus--color)',
        'offset' => 'var(--wp--custom--focus--offset)',
        'style'  => 'solid',
        'width'  => 'var(--wp--custom--focus--width)',
    ];
    $elements = base_elements()['styles']['elements'];
    $focus = base_elements()['settings']['custom']['focus'];

    expect($elements['link'][':focus-visible']['outline'])->toBe($ring)
        ->and($elements['button'][':focus-visible']['outline'])->toBe($ring)
        ->and($focus)->toBe(['width' => '2px', 'color' => 'var(--wp--preset--color--accent)', 'offset' => '2px'])
        ->and(base_elements_css())->toContain(
            ':where(button, input, select, textarea, summary, [tabindex]):focus-visible'
            . '{outline:var(--wp--custom--focus--width) solid var(--wp--custom--focus--color);outline-offset:var(--wp--custom--focus--offset);}',
        );
});

it('draws selected text in the selection colours (FR-012)', function () {
    expect(base_elements_css())->toContain(
        '::selection{background-color:var(--wp--preset--color--selection, var(--wp--preset--color--accent));'
        . 'color:var(--wp--preset--color--selection-text, var(--wp--preset--color--ink));}',
    );
});

it('leaves the Dark variation to the same button, link and focus rules, with its own colours', function () {
    $dark = base_elements('theme/styles/dark.json');

    expect($dark['styles'])->not->toHaveKey('elements')
        ->and($dark['styles'])->not->toHaveKey('css')
        ->and($dark['settings']['custom']['color'])->toHaveKeys(['onAccent', 'accentHover']);
});
