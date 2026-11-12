<?php

/**
 * Plugin Name: CoreX E2E pseudo-locale (test fixture)
 * Description: Test fixture. Marks every CoreX string for a request that asks for it, so the quality checks can find text that skips translation.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * The pseudo-locale run of the quality checks (spec 116, US-NFR-06, FR-016).
 *
 * For a request with the cookie `corex_pseudo_locale=1`, every string translated in the `corex`
 * text domain comes back as `⟦…⟧`: in PHP through the gettext filters, and in JavaScript through the
 * filters `@wordpress/i18n` runs for the domain. A word on a CoreX screen without the marks did
 * not go through a translation function. Every other request, and every other text domain, is left
 * alone, so the fixture changes nothing for the rest of the browser suite.
 *
 * Copied into `wp-content/mu-plugins/` with the other `corex-e2e-*.php` fixtures, by the CI jobs
 * and by `scripts/setup-wordpress.ps1`.
 */
if (($_COOKIE['corex_pseudo_locale'] ?? '') !== '1') {
    return;
}

$corexPseudo = static fn (string $text): string => '⟦' . $text . '⟧';

add_filter('gettext_corex', $corexPseudo);
add_filter('gettext_with_context_corex', $corexPseudo);
add_filter('ngettext_corex', $corexPseudo);
add_filter('ngettext_with_context_corex', $corexPseudo);

add_action('init', static function (): void {
    wp_add_inline_script(
        'wp-i18n',
        '( function () {'
        . ' var mark = function ( text ) { return "⟦" + text + "⟧"; };'
        . ' [ "i18n.gettext_corex", "i18n.gettext_with_context_corex", "i18n.ngettext_corex", "i18n.ngettext_with_context_corex" ].forEach( function ( name ) {'
        . ' wp.hooks.addFilter( name, "corex/pseudo-locale", mark );'
        . ' } );'
        . ' } )();',
        'after',
    );
});
