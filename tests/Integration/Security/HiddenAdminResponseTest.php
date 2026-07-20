<?php

/**
 * Integration tests for the hidden /wp-admin response (spec 069, FR-001 / SC-001).
 *
 * A hidden endpoint has to be indistinguishable from a page that was never there. The response
 * was a real theme 404 — and then printed "Function print_emoji_styles is deprecated" into its
 * body, which announces the hiding more loudly than the 404 conceals it. Nothing covered
 * render404() at all, which is exactly why that shipped.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;

function hiddenAdminGuard(): LoginRouteGuard
{
    update_option(LoginProtectionSettingsStore::OPTION, [
        'enabled' => true,
        'custom_slug' => 'team-login',
        'block_default_endpoints' => true,
    ]);

    return new LoginRouteGuard((new LoginProtectionSettingsStore())->current());
}

beforeEach(function () {
    $this->previousLoginSettings = get_option(LoginProtectionSettingsStore::OPTION, null);
    $this->emojiWasHooked = has_action('wp_print_styles', 'print_emoji_styles') !== false;
});

afterEach(function () {
    // These are global hooks. Put them back exactly as they were or every later test renders a
    // different <head> than it would have.
    remove_action('admin_print_styles', 'print_emoji_styles');

    if ($this->emojiWasHooked && has_action('wp_print_styles', 'print_emoji_styles') === false) {
        add_action('wp_print_styles', 'print_emoji_styles');
    }

    if ($this->previousLoginSettings === null) {
        delete_option(LoginProtectionSettingsStore::OPTION);
    } else {
        update_option(LoginProtectionSettingsStore::OPTION, $this->previousLoginSettings);
    }
});

it('moves the emoji shim so core can unhook it during an admin-context 404', function () {
    // Core's wp_enqueue_emoji_styles() reads `is_admin() ? 'admin_print_styles' : 'wp_print_styles'`
    // to decide what to unhook. WP_ADMIN is a constant we cannot unset, so on a hidden /wp-admin
    // core inspects admin_print_styles — and unless the shim is sitting there, it bails without
    // unhooking and the deprecated function runs.
    add_action('wp_print_styles', 'print_emoji_styles');

    hiddenAdminGuard()->dropAdminContext();

    expect(has_action('wp_print_styles', 'print_emoji_styles'))->toBeFalse()
        ->and(has_action('admin_print_styles', 'print_emoji_styles'))->not->toBeFalse();
});

// NOTE: that the relocation also causes core to *enqueue* wp-emoji-styles — the inline block a
// genuine front-end 404 carries, and the reason we move the shim rather than just deleting it —
// cannot be asserted here. wp_enqueue_emoji_styles() branches on is_admin(), and this suite does
// not run in an admin context, so core would inspect the front-end hook we just emptied and bail.
// That half of the contract is covered against a real request in tests/e2e/security-access.spec.js.

it('re-enqueues the block stylesheets core withholds from an admin request', function () {
    // wp_common_block_scripts_and_styles() returns early on is_admin() unless a block-editor
    // screen is up (wp-includes/script-loader.php), so the hidden 404 got no per-block sheets,
    // no wp-block-library, and never fired enqueue_block_assets. theme.json global styles have
    // no such gate, so the page arrived with design tokens and no block CSS at all — visibly
    // unstyled on a block theme whose style.css is metadata only.
    $guard = hiddenAdminGuard();
    $guard->dropAdminContext();

    expect(has_action('wp_enqueue_scripts', [$guard, 'enqueueBlockStyles']))->not->toBeFalse();

    $guard->enqueueBlockStyles();

    expect(wp_style_is('wp-block-library', 'enqueued'))->toBeTrue();

    // Leave no hook behind: this fires on every later test's wp_enqueue_scripts otherwise.
    remove_action('wp_enqueue_scripts', [$guard, 'enqueueBlockStyles'], 20);
    wp_dequeue_style('wp-block-library');
});

it('strips the admin bar init that would otherwise mark the missing page as admin', function () {
    add_action('template_redirect', '_wp_admin_bar_init', 0);

    hiddenAdminGuard()->dropAdminContext();

    expect(has_action('template_redirect', '_wp_admin_bar_init'))->toBeFalse();
});

it('puts nothing back when the emoji shim was already removed by someone else', function () {
    // A site that deliberately unhooked the shim must not find it silently reinstated.
    remove_action('wp_print_styles', 'print_emoji_styles');

    hiddenAdminGuard()->dropAdminContext();

    expect(has_action('admin_print_styles', 'print_emoji_styles'))->toBeFalse();
});
