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
     * @param string $variant   optional modifier appended as `corex-standalone--<variant>`
     * @param bool   $indexable whether this is a page meant to be found. Everything this class
     *                          rendered before spec 101 was an interstitial — a 503, a 403 — and
     *                          crawlers are asked to stay away from those. The coming-soon
     *                          fallback is a public page served with a 200, and is not.
     */
    public function document(string $title, string $bodyHtml, string $variant = '', bool $indexable = false): string
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
            . ($indexable ? '' : '<meta name="robots" content="noindex, nofollow" />')
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
     * A value somebody has to copy, in a read-only field with a button that copies it (spec 101,
     * FR-011a): the preview link, which is shown once and has to leave the page whole.
     *
     * The button is written `hidden` and the script shows it, so a page whose script does not run
     * has a field to select and copy by hand, and no button that does nothing. The script is
     * inlined, as the stylesheet is, and through WordPress's own function: a site that sends a
     * Content-Security-Policy adds its nonce with the `wp_inline_script_attributes` filter. What
     * the script says is written here, so it is translated like the rest of the page.
     *
     * What the button answered is written on the label's line, at its other end: over the button,
     * and where it takes no room of its own, so the card does not move when the answer comes.
     *
     * The field reads left to right whatever the page's direction: it is for an address or a code.
     *
     * @param string $id     the field's id, unique on the page
     * @param string $label  what the value is, shown above the field
     * @param string $value  the value, unescaped
     * @param string $action the button's words
     */
    public function copyField(string $id, string $label, string $value, string $action): string
    {
        return '<div class="corex-standalone__copy" data-corex-copy'
            . ' data-copied="' . esc_attr__('Copied.', 'corex') . '"'
            . ' data-manual="' . esc_attr__('It could not be copied for you. It is selected: copy it from the keyboard.', 'corex') . '">'
            . '<div class="corex-standalone__copy-head">'
            . '<label class="corex-standalone__copy-label" for="' . esc_attr($id) . '">' . esc_html($label) . '</label>'
            . '<p class="corex-standalone__copy-status" role="status" aria-live="polite" data-corex-copy-status></p>'
            . '</div>'
            . '<div class="corex-standalone__copy-row">'
            . '<input class="corex-standalone__copy-value" id="' . esc_attr($id) . '" type="text" readonly dir="ltr"'
            . ' spellcheck="false" autocomplete="off" value="' . esc_attr($value) . '" data-corex-copy-value />'
            . '<button type="button" class="button corex-standalone__copy-button" data-corex-copy-button hidden>'
            . self::copyIcons() . '<span>' . esc_html($action) . '</span></button>'
            . '</div>'
            . '</div>'
            . wp_get_inline_script_tag($this->read('js/corex-copy-field.js'));
    }

    /**
     * The copy button's two icons, of which the stylesheet shows one: two sheets until the value
     * has been copied, a tick for as long as the page says so.
     */
    private static function copyIcons(): string
    {
        $svg = '<svg class="corex-standalone__copy-icon corex-standalone__copy-icon--%s" viewBox="0 0 24 24" fill="none"'
            . ' stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
            . ' aria-hidden="true" focusable="false">%s</svg>';

        return sprintf($svg, 'copy', '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/>')
            . sprintf($svg, 'done', '<path d="M5 12.5l4.5 4.5L19 7.5"/>');
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

    /**
     * A file this document inlines: a stylesheet it cannot be read without, or the copy field's
     * script, without which the field has no button and is copied by hand.
     *
     * The empty-string fallback is kept — a 403 that renders unstyled is still better than a 403
     * that fatals — but it is no longer silent. Every consumer of this class is a page shown when
     * something has already gone wrong, and the failure it produces is a grey canvas with a white
     * box on it: indistinguishable, to the person reporting it, from having no error page at all.
     * Spec 083 exists because that description went unexamined for a release, so the one way this
     * class can still produce it now says so where an operator will find it.
     */
    private function read(string $relative): string
    {
        $file = $this->assetsPath . '/' . ltrim($relative, '/');

        if (! is_file($file)) {
            // Written directly rather than through BootLogger, which needs the container: this class
            // is deliberately constructible without one (see fromCore()), because the pages it
            // renders are the ones served when the normal lifecycle did not complete. The line
            // matches BootLogger's own format so both land the same way in the debug log.
            error_log(sprintf('[Corex] WARNING: standalone admin asset missing: %s', $file));

            return '';
        }

        return (string) file_get_contents($file);
    }
}
