<?php

/**
 * Plugin Name: CoreX E2E Arabic proof (test fixture)
 * Description: Test fixture. Runs a request in Arabic when it asks to, and records every CoreX string the Arabic catalogue did not answer.
 * Version: 1.0.0
 *
 * @package Corex\Tests\E2E
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

/**
 * The Arabic proof of the quality checks (spec 121, US-NFR-02, DECISIONS #467).
 *
 * For a request with the cookie `corex_e2e_locale=ar`, WordPress runs in Arabic, right to left,
 * for that request only: the site's own language is not changed, so the rest of the browser suite
 * is untouched. Every string of the `corex` text domain that a PHP or JavaScript call asked for and
 * got back unchanged is recorded, unless all its words are names the glossary keeps in English
 * (`plugins/corex-core/languages/glossary-ar.md`). The page reports them in
 * `window.__corexArabicMissing`, and a REST answer in the header `X-Corex-Arabic-Missing`.
 *
 * It asks the translation functions, not the page: a name or an address the site holds is data, not
 * a CoreX string, and is never counted. The page also says whether the catalogue was loaded at all
 * (`window.__corexArabicCatalogue`), so a missing catalogue fails the proof as itself.
 *
 * Copied into `wp-content/mu-plugins/` with the other `corex-e2e-*.php` fixtures, by the CI jobs
 * and by `scripts/setup-wordpress.ps1`.
 */
if (($_COOKIE['corex_e2e_locale'] ?? '') !== 'ar') {
    return;
}

/** Words the catalogue keeps in English: product names, formats and code. */
const COREX_E2E_ARABIC_KEEP = [
    'CoreX', 'Corex', 'WordPress', 'WooCommerce', 'WP-CLI', 'PHP', 'MySQL', 'SMTP', 'DKIM', 'SPF', 'DMARC', 'REST', 'API',
    'JSON', 'CSV', 'XML', 'PDF', 'Excel', 'HTML', 'CSS', 'JavaScript', 'URL', 'SVG', 'WebP', 'ACF', 'Polylang', 'WPML',
    'Gutenberg', 'reCAPTCHA', 'hCaptcha', 'Turnstile', 'Cloudflare', 'Google', 'Gmail', 'Outlook', 'GitHub', 'Azure',
    'cron', 'OPcache', 'UTF-8', 'SSO', 'OAuth', 'IP', 'Space Grotesk', 'JetBrains Mono', 'IBM Plex Sans Arabic',
    'Facebook', 'LinkedIn', 'WhatsApp', 'Honeypot', 'Blog Pro', 'Pro', 'HTTPS', 'HTTP', 'CDN', 'CV', 'WP-Cron', 'SSL',
];

/** Whether a string that came back unchanged still holds an English word. */
function corex_e2e_arabic_counts(string $text): bool
{
    $words = preg_replace(
        [
            '/<[^>]+>/',                                   // markup
            '/%(?:\d+\$)?[-+ 0#]*\d*(?:\.\d+)?[a-zA-Z%]/', // placeholders
            '/\{[a-zA-Z_][a-zA-Z0-9_]*\}/',                // named slots
            '/\S+@\S+/',                                   // addresses
            '#\S*(?:https?://|www\.|/)\S*#',               // links and paths
            '/`[^`]*`/',                                   // code
            '/\b[a-z0-9]+(?:[_\-.:][a-z0-9]+)+\b/',        // identifiers: corex_flag, corex-forms, wp.hooks
        ],
        ' ',
        $text,
    ) ?? '';
    // Longest first, so `cron` does not take the end of `WP-Cron` and leave `WP` behind.
    $keep = COREX_E2E_ARABIC_KEEP;
    usort($keep, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    $words = str_ireplace($keep, ' ', $words);

    return preg_match('/[A-Za-z]{2,}/', $words) === 1;
}

/** @return array<string,true> */
function &corex_e2e_arabic_missing(): array
{
    static $missing = [];

    return $missing;
}

function corex_e2e_arabic_record(string $translation, string ...$originals): string
{
    if (in_array($translation, $originals, true) && corex_e2e_arabic_counts($translation)) {
        corex_e2e_arabic_missing()[$translation] = true;
    }

    return $translation;
}

$corexArabic = static fn (): string => 'ar';
add_filter('pre_determine_locale', $corexArabic, PHP_INT_MAX);
add_filter('locale', $corexArabic, PHP_INT_MAX);

// Right to left even where WordPress's own Arabic pack is not installed, which is what sets it.
add_action('after_setup_theme', static function (): void {
    if (isset($GLOBALS['wp_locale']) && $GLOBALS['wp_locale'] instanceof WP_Locale) {
        $GLOBALS['wp_locale']->text_direction = 'rtl';
    }
}, 0);

add_filter('gettext_corex', static fn (string $translation, string $text): string => corex_e2e_arabic_record($translation, $text), PHP_INT_MAX, 2);
add_filter(
    'gettext_with_context_corex',
    static fn (string $translation, string $text): string => corex_e2e_arabic_record($translation, $text),
    PHP_INT_MAX,
    2,
);
add_filter(
    'ngettext_corex',
    static fn (string $translation, string $single, string $plural): string => corex_e2e_arabic_record($translation, $single, $plural),
    PHP_INT_MAX,
    3,
);
add_filter(
    'ngettext_with_context_corex',
    static fn (string $translation, string $single, string $plural): string => corex_e2e_arabic_record($translation, $single, $plural),
    PHP_INT_MAX,
    3,
);

add_action('init', static function (): void {
    $names = COREX_E2E_ARABIC_KEEP;
    usort($names, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
    $keep = wp_json_encode($names);

    // The same test in the browser, for the strings `@wordpress/i18n` translates.
    wp_add_inline_script(
        'wp-i18n',
        '( function () {'
        . ' var keep = ' . $keep . ';'
        . ' window.__corexArabicMissing = window.__corexArabicMissing || [];'
        . ' var counts = function ( text ) {'
        . '  var words = String( text ).replace( /<[^>]+>/g, " " ).replace( /%(?:\\d+\\$)?[-+ 0#]*\\d*(?:\\.\\d+)?[a-zA-Z%]/g, " " )'
        . '   .replace( /\\{[a-zA-Z_][a-zA-Z0-9_]*\\}/g, " " ).replace( /\\S+@\\S+/g, " " ).replace( /\\S*(?:https?:\\/\\/|www\\.|\\/)\\S*/g, " " )'
        . '   .replace( /`[^`]*`/g, " " ).replace( /\\b[a-z0-9]+(?:[_\\-.:][a-z0-9]+)+\\b/g, " " );'
        . '  keep.forEach( function ( name ) { words = words.split( name ).join( " " ); } );'
        . '  return /[A-Za-z]{2,}/.test( words );'
        . ' };'
        . ' var record = function ( translation, a, b ) {'
        . '  if ( ( translation === a || translation === b ) && counts( translation ) && window.__corexArabicMissing.indexOf( translation ) < 0 ) {'
        . '   window.__corexArabicMissing.push( translation );'
        . '  }'
        . '  return translation;'
        . ' };'
        . ' wp.hooks.addFilter( "i18n.gettext_corex", "corex/arabic-proof", function ( t, text ) { return record( t, text ); }, 999 );'
        . ' wp.hooks.addFilter( "i18n.gettext_with_context_corex", "corex/arabic-proof", function ( t, text ) { return record( t, text ); }, 999 );'
        . ' wp.hooks.addFilter( "i18n.ngettext_corex", "corex/arabic-proof", function ( t, s, p ) { return record( t, s, p ); }, 999 );'
        . ' wp.hooks.addFilter( "i18n.ngettext_with_context_corex", "corex/arabic-proof", function ( t, s, p ) { return record( t, s, p ); }, 999 );'
        . ' } )();',
        'after',
    );
});

$corexArabicReport = static function (): void {
    printf(
        '<script id="corex-e2e-arabic">window.__corexArabicCatalogue = %s;'
        . ' window.__corexArabicMissing = ( window.__corexArabicMissing || [] ).concat( %s );</script>',
        wp_json_encode(__('Settings', 'corex') !== 'Settings'),
        wp_json_encode(array_keys(corex_e2e_arabic_missing())),
    );
};
add_action('admin_print_footer_scripts', $corexArabicReport, PHP_INT_MAX);
add_action('wp_print_footer_scripts', $corexArabicReport, PHP_INT_MAX);
add_action('login_footer', $corexArabicReport, PHP_INT_MAX);

add_filter('rest_post_dispatch', static function ($response) {
    $missing = array_keys(corex_e2e_arabic_missing());

    if ($missing !== [] && $response instanceof WP_REST_Response) {
        $response->header('X-Corex-Arabic-Missing', rawurlencode((string) wp_json_encode($missing)));
    }

    return $response;
}, PHP_INT_MAX);
