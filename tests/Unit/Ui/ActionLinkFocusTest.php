<?php

/**
 * The hero and call-to-action buttons keep a focus ring under a client theme (spec 115, US-SAFE-15; spec 134,
 * "Found while building slice 2", item 2). A client theme defines none of CoreX's focus or palette tokens, and one
 * `var()` with no value and no fallback makes the whole declaration invalid, so the button lost even the
 * browser's own ring (WCAG 2.2, 2.4.7). Every token the rule names carries a fallback.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

/** The declarations of the focus rule in `action-link.css`, comments removed. */
function actionLinkFocusDeclarations(): string
{
    $css = (string) file_get_contents(dirname(__DIR__, 3) . '/addons/corex-ui/assets/action-link.css');
    $css = (string) preg_replace('~/\*.*?\*/~s', '', $css);
    preg_match('~:focus-visible\s*\{([^}]*)\}~', $css, $rule);

    return $rule[1] ?? '';
}

it('draws a ring when the focus tokens are missing, as under a client theme', function () {
    $declarations = actionLinkFocusDeclarations();
    preg_match_all('~var\(\s*(--[a-z0-9-]+)\s*(,)?~', $declarations, $uses, PREG_SET_ORDER);

    expect($declarations)->not->toBe('')
        ->and($uses)->not->toBeEmpty();
    foreach ($uses as $use) {
        expect($use[2] ?? '')->toBe(',', $use[1] . ' needs a fallback');
    }
});

it('keeps the CoreX theme ring: the focus width and offset tokens, drawn in surface', function () {
    $declarations = actionLinkFocusDeclarations();

    expect($declarations)->toContain('var(--wp--custom--focus--width')
        ->and($declarations)->toContain('var(--wp--preset--color--surface')
        ->and($declarations)->toContain('var(--wp--custom--focus--offset');
});
