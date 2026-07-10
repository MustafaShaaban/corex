<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Admin;

defined('ABSPATH') || exit;

/**
 * Renders a complete, self-contained HTML document for a CoreX interstitial that is
 * served OUTSIDE the enqueued admin shell — the Maintenance-mode 503 shown to anonymous
 * front-end visitors, and the menu-level access-denied / request-access 403. Those
 * responses cannot rely on a registered CoreX stylesheet (they short-circuit the normal
 * page lifecycle), so this class inlines the scoped `--corex-admin-*` token adapter plus
 * the standalone sheet, producing a fully branded page with no external CSS request.
 *
 * The supplied body HTML is already-escaped, trusted CoreX markup; the document scaffold
 * escapes the dynamic attributes/title it introduces.
 */
final class StandalonePage
{
    public function __construct(
        private readonly string $assetsPath,
        private readonly string $assetsUrl,
    ) {
    }

    /**
     * Build a renderer bound to the corex-core asset directory.
     */
    public static function fromCore(): self
    {
        $base = defined('COREX_CORE_FILE') ? COREX_CORE_FILE : __FILE__;

        return new self(
            rtrim(plugin_dir_path($base), '/\\') . '/assets',
            plugins_url('assets', $base),
        );
    }

    /**
     * A full `<!DOCTYPE html>` document wrapping $bodyHtml in the branded standalone body.
     *
     * @param string $variant optional modifier appended as `corex-standalone--<variant>`
     */
    public function document(string $title, string $bodyHtml, string $variant = ''): string
    {
        $lang       = function_exists('get_bloginfo') ? (string) get_bloginfo('language') : 'en-US';
        $charset    = function_exists('get_bloginfo') ? (string) get_bloginfo('charset') : '';
        $charset    = $charset !== '' ? $charset : 'UTF-8';
        $dir        = function_exists('is_rtl') && is_rtl() ? 'rtl' : 'ltr';
        $appearance = function_exists('apply_filters')
            ? (string) apply_filters('corex_admin_appearance', 'system')
            : 'system';

        $bodyClass = 'corex-standalone corex-admin-screen';
        if (in_array($appearance, ['light', 'dark'], true)) {
            $bodyClass .= ' corex-appearance-' . $appearance;
        }
        $variant = preg_replace('/[^a-z0-9-]/', '', $variant) ?? '';
        if ($variant !== '') {
            $bodyClass .= ' corex-standalone--' . $variant;
        }

        return '<!DOCTYPE html>'
            . '<html lang="' . esc_attr($lang) . '" dir="' . esc_attr($dir) . '">'
            . '<head>'
            . '<meta charset="' . esc_attr($charset) . '" />'
            . '<meta name="viewport" content="width=device-width, initial-scale=1" />'
            . '<meta name="robots" content="noindex, nofollow" />'
            . '<title>' . esc_html($title) . '</title>'
            . '<style>' . $this->inlineStyles() . '</style>'
            . '</head>'
            . '<body class="' . esc_attr($bodyClass) . '">'
            . $bodyHtml
            . '</body></html>';
    }

    /**
     * A short branded notice page (403/404/expired-link and similar interstitials that fire from
     * an admin-post front controller, outside the admin shell). Escapes its own dynamic values.
     */
    public function notice(string $title, string $message, string $backUrl = '', string $backLabel = ''): string
    {
        $actions = '';
        if ($backUrl !== '') {
            $actions = '<div class="corex-standalone__actions">'
                . '<a class="button button-primary" href="' . esc_url($backUrl) . '">'
                . esc_html($backLabel) . '</a></div>';
        }

        $body = '<main class="corex-standalone__card" role="main">'
            . '<span class="corex-standalone__mark" aria-hidden="true">' . self::brandMark() . '</span>'
            . '<p class="corex-standalone__eyebrow">Corex</p>'
            . '<h1 class="corex-standalone__title">' . esc_html($title) . '</h1>'
            . '<p class="corex-standalone__text">' . esc_html($message) . '</p>'
            . $actions . '</main>';

        return $this->document($title, $body, 'notice');
    }

    /**
     * The five-square CoreX brand mark as an inline SVG in `currentColor`, so the surrounding
     * standalone styles own its size and colour. Shared by every standalone interstitial.
     */
    public static function brandMark(): string
    {
        return '<svg viewBox="0 0 48 48" fill="currentColor" aria-hidden="true" focusable="false">'
            . '<rect x="3" y="3" width="12" height="12" rx="2.5"/>'
            . '<rect x="33" y="3" width="12" height="12" rx="2.5"/>'
            . '<rect x="18" y="18" width="12" height="12" rx="2.5"/>'
            . '<rect x="3" y="33" width="12" height="12" rx="2.5"/>'
            . '<rect x="33" y="33" width="12" height="12" rx="2.5"/></svg>';
    }

    /**
     * The inlined token adapter (with font URLs absolutized) followed by the standalone sheet.
     */
    private function inlineStyles(): string
    {
        $tokens = $this->read('css/corex-admin-tokens.css');

        // The adapter's @font-face src is relative to its own directory; absolutize it so the
        // brand fonts still resolve when the sheet is inlined into a page at the site root.
        $tokens = str_replace('url(../fonts/', 'url(' . $this->assetsUrl . '/fonts/', $tokens);

        return $tokens . "\n" . $this->read('css/corex-admin-standalone.css');
    }

    private function read(string $relative): string
    {
        $file = $this->assetsPath . '/' . ltrim($relative, '/');

        return is_file($file) ? (string) file_get_contents($file) : '';
    }
}
