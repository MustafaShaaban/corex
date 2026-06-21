<?php

/**
 * Corrective Spec 060 asset-scoping coverage for every current CoreX admin screen.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\AdminUi\CorexAdminAssets;

it('recognizes every current CoreX admin screen and rejects unrelated admin hooks', function () {
    $assets = new CorexAdminAssets();

    foreach ([
        'toplevel_page_corex-settings',
        'corex_page_corex-addons',
        'corex_page_corex-data',
        'corex_page_corex-settings-config',
        'corex_page_corex-setup',
        'corex_page_corex-insights',
        'corex_page_corex-page-example',
    ] as $hook) {
        expect($assets->supports($hook))->toBeTrue($hook);
    }

    foreach (['dashboard', 'plugins.php', 'settings_page_general', '', 'corex-settings'] as $hook) {
        expect($assets->supports($hook))->toBeFalse($hook);
    }
});

it('enqueues the shared shell only for a CoreX screen', function () {
    $assets = new CorexAdminAssets();

    Functions\expect('wp_enqueue_style')->once()->with('corex-admin-shell');
    $assets->enqueue('corex_page_corex-data');

    $assets->enqueue('plugins.php');
});
