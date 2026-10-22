<?php

/**
 * Plugin Name: CoreX E2E planted defects (test fixture)
 * Description: Test fixture. Plants one defect per quality check, on a throwaway branch only, to show each check fails and names it (spec 116).
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

// TC-NFR-01-04 and TC-NFR-05-06: a new CoreX admin page nobody told the checks about, with no shell
// and no state table.
add_action('admin_menu', static function (): void {
    add_submenu_page('corex-settings', 'Planted screen', 'Planted screen', 'manage_options', 'corex-planted', static function (): void {
        echo '<div class="wrap"><h1>Planted screen</h1></div>';
    });
}, 99);

// TC-NFR-03-03 (a 16px icon button), TC-NFR-06-02 (a raw key) and TC-NFR-06-04 (an untranslated
// label), in the shell header of every CoreX screen.
add_filter('corex_admin_header_actions', static function (string $html): string {
    return $html
        . '<button type="button" aria-label="Planted" class="corex-planted-tiny" style="inline-size:16px;block-size:16px;padding:0;min-inline-size:0;min-block-size:0;border:0;">x</button>'
        . '<span class="corex-planted-key">corex_owner_key</span>'
        . '<span class="corex-planted-label">Planted untranslated label</span>';
});

// TC-NFR-01-02 (2px of sideways scroll in right to left on Access), TC-NFR-04-02 (a 947px inbox
// table), a focus ring removed (TC-NFR-03-04) and a heading that moves forever (TC-NFR-03-06,
// TC-NFR-07-04).
add_action('admin_head', static function (): void {
    echo '<style id="corex-planted-defects">'
        . '[dir="rtl"] .corex-framework_page_corex-access .corex-admin__header { margin-inline-end: -2px; }'
        . '.corex-inbox table { min-inline-size: 947px; }'
        . '.corex-admin__nav-item:focus, .corex-admin__nav-item:focus-visible { outline: none !important; box-shadow: none !important; }'
        . '@keyframes corex-planted { from { transform: translateX(0); } to { transform: translateX(4px); } }'
        . '.corex-admin__heading { animation: corex-planted 1s infinite alternate; }'
        . '</style>';
});
