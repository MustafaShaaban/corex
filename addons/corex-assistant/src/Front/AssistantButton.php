<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Front;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;
use Corex\Assistant\Http\AssistantController;
use Corex\Blocks\BlockRenderer;

/**
 * The assistant's button on every public page (spec 129, FR-002 and FR-003).
 *
 * A block (`corex/assistant`), so its small script and stylesheet are WordPress's to load, and printed
 * in `wp_footer` rather than placed in a template: it belongs to every page, and a template an editor
 * customised could not lose it. The chat itself is a separate script the button loads on its first
 * use, so a page that is only read pays for the button alone.
 */
final class AssistantButton implements BlockRenderer
{
    public const NAME = 'corex/assistant';

    /** The panel's id, which the button controls. */
    public const PANEL_ID = 'corex-assistant-panel';

    /**
     * @param \Closure(): string $form the hand-off form's endpoint, '' when corex-forms is not active
     */
    public function __construct(
        private readonly AssistantSettings $settings,
        private readonly AssistantStrings $strings,
        private readonly \Closure $form,
    ) {
    }

    /** Early in the footer, so the block's script and style are enqueued before WordPress prints them. */
    public function printInFooter(): void
    {
        if ($this->shows()) {
            echo do_blocks('<!-- wp:' . self::NAME . ' /-->'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- markup() escapes every value
        }
    }

    /** On a public page, with the assistant on and at least one model service keyed. */
    public function shows(): bool
    {
        return ! is_admin() && ! is_feed() && ! is_embed() && ! is_customize_preview() && $this->settings->live();
    }

    public function render(array $attributes, string $content, object $block): string
    {
        $locale = self::language(determine_locale());
        $phone = apply_filters('corex_assistant_phone_countries', ['default' => '', 'first' => []]);
        $phone = is_array($phone) ? $phone : [];

        return self::markup([
            'locale' => $locale,
            'dir' => is_rtl() ? 'rtl' : 'ltr',
            'endpoint' => (string) rest_url(AssistantController::ROUTE_NAMESPACE . AssistantController::ROUTE),
            'form' => ($this->form)(),
            'nonce' => wp_create_nonce('wp_rest'),
            'mark' => $this->settings->mark(),
            'country' => self::iso($phone['default'] ?? ''),
            'countriesFirst' => implode(',', array_filter(array_map([self::class, 'iso'], (array) ($phone['first'] ?? [])))),
            'siteZone' => (string) wp_timezone_string(),
            'strings' => $this->strings->for($locale),
        ]);
    }

    /** A WordPress locale as the language the chat and the knowledge are keyed by: `ar`, `en`, `pt`. */
    public static function language(string $locale): string
    {
        $language = strtolower((string) strtok($locale, '_-'));

        return preg_match('/^[a-z]{2,3}$/', $language) === 1 ? $language : 'en';
    }

    /**
     * The button, the invitation beside it, the empty panel, and the chat's settings in `data-`
     * attributes. Every value is escaped here.
     *
     * @param array{locale: string, dir: string, endpoint: string, form: string, nonce: string, mark: string, country: string, countriesFirst: string, siteZone: string, strings: array<string, string|list<string>>} $config
     */
    public static function markup(array $config): string
    {
        $strings = $config['strings'];
        $text = static fn (string $key): string => is_string($strings[$key] ?? null) ? $strings[$key] : '';

        return '<div class="corex-assistant" data-corex-assistant dir="' . esc_attr($config['dir']) . '"'
            . ' data-locale="' . esc_attr($config['locale']) . '"'
            . ' data-endpoint="' . esc_url($config['endpoint']) . '"'
            . ' data-form="' . esc_url($config['form']) . '"'
            . ' data-nonce="' . esc_attr($config['nonce']) . '"'
            . ' data-mark="' . esc_url($config['mark']) . '"'
            . ' data-country="' . esc_attr($config['country']) . '"'
            . ' data-countries-first="' . esc_attr($config['countriesFirst']) . '"'
            . ' data-site-zone="' . esc_attr($config['siteZone']) . '"'
            . ' data-strings="' . esc_attr((string) wp_json_encode($strings)) . '">'
            . '<div class="corex-assistant__teaser" hidden>'
            . '<p><strong>' . esc_html($text('teaserTitle')) . '</strong> <span>' . esc_html($text('teaserText')) . '</span></p>'
            . '<button type="button" class="corex-assistant__dismiss" aria-label="' . esc_attr($text('dismiss')) . '">'
            . self::icon('M18 6 6 18M6 6l12 12', 16)
            . '</button></div>'
            . '<button type="button" class="corex-assistant__fab" aria-expanded="false" aria-controls="' . esc_attr(self::PANEL_ID) . '"'
            . ' aria-label="' . esc_attr($text('open')) . '">' . self::mark($config['mark'])
            . '<span class="corex-assistant__online" aria-hidden="true"></span></button>'
            . '<div class="corex-assistant__panel" id="' . esc_attr(self::PANEL_ID) . '" role="dialog" aria-label="'
            . esc_attr($text('name')) . '" hidden></div>'
            . '</div>';
    }

    /** The chosen image, else a speech bubble drawn in the button's text colour. */
    private static function mark(string $url): string
    {
        if ($url === '') {
            return self::icon('M21 12a8 8 0 0 1-11.6 7.1L4 20.5l1.4-5.1A8 8 0 1 1 21 12Z', 28, 'corex-assistant__mark');
        }

        return '<img class="corex-assistant__mark" src="' . esc_url($url) . '" alt="" width="32" height="32" decoding="async" />';
    }

    private static function icon(string $path, int $size, string $class = ''): string
    {
        return '<svg' . ($class !== '' ? ' class="' . esc_attr($class) . '"' : '') . ' viewBox="0 0 24 24" width="' . $size . '" height="' . $size
            . '" aria-hidden="true" focusable="false"><path d="' . esc_attr($path)
            . '" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/></svg>';
    }

    /** An ISO 3166 country code, or ''. */
    private static function iso(mixed $code): string
    {
        $code = is_string($code) ? strtoupper(trim($code)) : '';

        return preg_match('/^[A-Z]{2}$/', $code) === 1 ? $code : '';
    }
}
