<?php

/**
 * The one predicate deciding whether an admin screen is CoreX's (spec 097, FR-004).
 *
 * This matrix moved here from `CorexAdminAssetsTest` when spec 097 extracted the rule: it was never
 * about assets, and three consumers now depend on the same answer. A screen counted as ours by the
 * stylesheet but not by the help removal renders the CoreX surface with a WordPress Help tab on it.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Config\AdminUi\CorexAdminAssets;
use Corex\Config\AdminUi\CorexScreens;

it('recognizes every current CoreX admin screen and rejects unrelated admin hooks', function () {
    foreach ([
        // The toplevel Overview.
        'toplevel_page_corex-settings',
        // Submenu pages: WordPress derives the prefix from the "COREX FRAMEWORK" menu title,
        // so the real get_current_screen() id is `corex-framework_page_*` (not `corex_page_*`).
        // Both forms must be recognised so the body class lands on every CoreX screen.
        'corex-framework_page_corex-settings-config',
        'corex-framework_page_corex-addons',
        'corex-framework_page_corex-data',
        'corex-framework_page_corex-insights',
        'corex-framework_page_corex-setup',
        'corex-framework_page_corex-page-example',
        'corex_page_corex-addons',
        'corex_page_corex-data',
        'corex_page_corex-settings-config',
        'corex_page_corex-setup',
        'corex_page_corex-insights',
        'corex_page_corex-page-example',
        // The Guides screen, which is where somebody looks for help now that no screen has a tab.
        'corex_page_corex-guides',
        'corex-framework_page_corex-guides',
        // Blog Pro and Forms, named because spec 097's browser matrix asserts against them.
        'corex_page_corex-blog-pro',
        'corex_page_corex-forms',
    ] as $hook) {
        expect(CorexScreens::supports($hook))->toBeTrue($hook);
    }

    foreach ([
        'dashboard',
        'plugins.php',
        'settings_page_general',
        '',
        'corex-settings',
        // A non-CoreX page that merely contains "corex" must not match.
        'toplevel_page_corexextra',
        'corex_page_other-data',
        // The screens spec 097 asserts still have their own Help tab. If any of these ever matched,
        // CoreX would be stripping help from wp-admin at large.
        'edit',
        'edit-post',
        'options-general',
        'plugins',
    ] as $hook) {
        expect(CorexScreens::supports($hook))->toBeFalse($hook);
    }
});

// The delegate exists so callers that already had `CorexAdminAssets::supports()` did not have to
// change. It is worth one assertion that the two cannot drift apart.
it('answers identically through the asset loader that delegates to it', function () {
    $assets = new CorexAdminAssets();

    foreach (['toplevel_page_corex-settings', 'corex_page_corex-guides', 'dashboard', ''] as $hook) {
        expect($assets->supports($hook))->toBe(CorexScreens::supports($hook), $hook);
    }
});
