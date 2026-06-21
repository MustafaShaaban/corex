<?php

/**
 * Spec 060 / M6 US3 — the CoreX Add-ons styling is scoped, token-only, and never global.
 *
 * It consumes only the scoped --corex-admin-* adapter (no --wp--preset--, no
 * :root/html/body), and the screen enqueues it only on the CoreX Add-ons screen hook
 * declaring the adapter as a dependency — so it never restyles wp-admin globally and
 * never loads on the public frontend.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

function addonsCss(): string
{
    return (string) file_get_contents(ThemeContract::root() . '/plugins/corex-config/assets/addons.css');
}

function addonsScreenSource(): string
{
    return (string) file_get_contents(
        ThemeContract::root() . '/plugins/corex-config/src/Addons/AddonsScreen.php',
    );
}

it('ships the add-ons stylesheet consuming only the scoped admin adapter', function () {
    $css = addonsCss();

    expect($css)->toContain('--corex-admin-')
        ->and($css)->not->toContain('--wp--preset--')
        ->and($css)->not->toMatch('/(?:^|,)\s*(?::root|html|body)\b/m');
});

it('styles the truthful state badge tones from admin success/warning/error roles', function () {
    $css = addonsCss();

    expect($css)->toContain('.corex-badge--success')
        ->and($css)->toContain('.corex-badge--warning')
        ->and($css)->toContain('.corex-badge--danger')
        ->and($css)->toContain('.corex-badge--neutral')
        ->and($css)->toContain('var(--corex-admin-success)')
        ->and($css)->toContain('var(--corex-admin-error)');
});

it('enqueues the add-ons style only on its screen, depending on the scoped adapter', function () {
    $source = addonsScreenSource();

    // Conditional on the stored screen hook (Principle VI) — not a global admin enqueue.
    expect($source)->toContain("wp_enqueue_style(")
        ->and($source)->toContain("'corex-addons'")
        ->and($source)->toContain("['corex-admin-tokens']")
        ->and($source)->toContain('$hook !== $this->hook');
});
