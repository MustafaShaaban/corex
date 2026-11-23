<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\Languages\Language;
use Corex\Blocks\Languages\LanguageSource;

/**
 * Server render for `corex/language-switcher` (spec 137, User Story 5; FR-031, FR-032, FR-032a;
 * DECISIONS #694, #697, #702). It reads the request's languages from the bound {@see LanguageSource}
 * and prints one root element; the block registrar adds what an editor set on the block to it.
 *
 * - Fewer than two languages: nothing at all (FR-031).
 * - `menu` (the default): the kit's ghost button naming the current language, over the kit's Dropdown
 *   listing every language. The block's style shows it as the list form where the browser runs no
 *   script, and its view script opens and closes it.
 * - `list`: every language in a row; the current one ticked, marked `aria-current` and not a link.
 * - `toggle`: with exactly two languages, one link naming the other language, its visible text its
 *   whole accessible name. With three or more, the list.
 *
 * With no language marked current, the menu and the toggle have nothing to name and print the list.
 * Each name carries its own `lang` and `dir`, and each link its `hreflang`.
 */
final class LanguageSwitcherRenderer implements BlockRenderer
{
    private const FORMS = ['menu', 'list', 'toggle'];

    /** The kit's chevron, pointing down; it turns when the menu is open. */
    private const CHEVRON = '<svg class="corex-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="m6 9 6 6 6-6" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    /** The kit's tick, which is not mirrored in right-to-left pages. */
    private const TICK = '<svg class="corex-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">'
        . '<path d="M20 6 9 17l-5-5" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    public function __construct(private readonly LanguageSource $source)
    {
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $languages = array_values(array_filter(
            $this->source->languages(),
            static fn (mixed $language): bool => $language instanceof Language,
        ));

        if (count($languages) < 2) {
            return '';
        }

        $current = $this->current($languages);

        return match ($this->form($attributes, count($languages), $current)) {
            'menu'   => $this->menu($languages, $current),
            'toggle' => $this->toggle($languages, $current),
            default  => $this->root('list', $this->list($languages, $current)),
        };
    }

    /**
     * The form to print: the one chosen when it can be drawn, the list otherwise.
     *
     * @param array<string, mixed> $attributes
     */
    private function form(array $attributes, int $count, ?Language $current): string
    {
        $chosen = in_array($attributes['form'] ?? null, self::FORMS, true) ? $attributes['form'] : 'menu';

        if ($current === null || ($chosen === 'toggle' && $count !== 2)) {
            return 'list';
        }

        return $chosen;
    }

    /**
     * The first language the source marks current.
     *
     * @param list<Language> $languages
     */
    private function current(array $languages): ?Language
    {
        foreach ($languages as $language) {
            if ($language->current) {
                return $language;
            }
        }

        return null;
    }

    /** @param list<Language> $languages */
    private function menu(array $languages, Language $current): string
    {
        $id = wp_unique_id('corex-language-switcher-');

        $button = '<button type="button" class="wp-element-button corex-language-switcher__button"'
            . ' aria-expanded="false" aria-controls="' . esc_attr($id) . '">'
            . $this->name($current)
            . '<span class="screen-reader-text">, ' . esc_html__('choose a language', 'corex') . '</span>'
            . self::CHEVRON
            . '</button>';

        return $this->root('menu', $button . $this->list($languages, $current, $id));
    }

    /** @param list<Language> $languages */
    private function toggle(array $languages, Language $current): string
    {
        foreach ($languages as $language) {
            if ($language !== $current) {
                return $this->root('toggle', $this->link($language, isName: true));
            }
        }

        return '';
    }

    /**
     * Every language as an item of one list. Given an id, the list is the menu's Dropdown too.
     *
     * @param list<Language> $languages
     */
    private function list(array $languages, ?Language $current, string $id = ''): string
    {
        $items = '';
        foreach ($languages as $language) {
            $items .= '<li>' . ($language === $current ? $this->currentItem($language) : $this->link($language)) . '</li>';
        }

        $attributes = $id === ''
            ? ' class="corex-language-switcher__list"'
            : ' id="' . esc_attr($id) . '" class="corex-language-switcher__list corex-dropdown__menu"';

        return '<ul' . $attributes . ' aria-label="' . esc_attr__('Languages', 'corex') . '">' . $items . '</ul>';
    }

    /**
     * A language as a link. In the toggle the link is the name and carries its `lang` and `dir`
     * (FR-032a). In a list the name inside the link carries them, so each row keeps the page's
     * direction and the list's rows line up.
     */
    private function link(Language $language, bool $isName = false): string
    {
        $open = '<a class="corex-language-switcher__item" href="' . esc_url($language->url) . '"'
            . ' hreflang="' . esc_attr($language->code) . '"';

        return $isName
            ? $open . $this->languageAttributes($language) . '>' . esc_html($language->name) . '</a>'
            : $open . '>' . $this->name($language) . '</a>';
    }

    private function currentItem(Language $language): string
    {
        return '<span class="corex-language-switcher__item" aria-current="true">'
            . self::TICK . $this->name($language) . '</span>';
    }

    private function name(Language $language): string
    {
        return '<span' . $this->languageAttributes($language) . '>' . esc_html($language->name) . '</span>';
    }

    private function languageAttributes(Language $language): string
    {
        return ' lang="' . esc_attr($language->code) . '" dir="' . esc_attr($language->direction) . '"';
    }

    private function root(string $form, string $inner): string
    {
        return '<div class="corex-language-switcher corex-language-switcher--' . $form . '">' . $inner . '</div>';
    }
}
