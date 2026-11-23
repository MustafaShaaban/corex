<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

use Corex\Blocks\BlockRenderer;

/**
 * Renders `corex/mega-menu`, an item of a Navigation block that opens a panel (spec 137, FR-011,
 * FR-012, FR-016, FR-017; DECISIONS #691).
 *
 * The item is a list item of the menu's list holding a button and the panel, a template part drawn
 * by {@see MegaMenuPanels}. The button says whether the panel is open and which panel it controls;
 * the panel is a group named by its button. Every copy gets its own ids, so the same menu drawn in
 * the bar and in the phone menu has no id twice. The panel is hidden until the block's script opens
 * it; without the script its style shows it while the pointer or the focus is inside the item.
 *
 * Nothing is drawn for an item with no label or no panel, or whose panel is missing, empty or already
 * being drawn: never a trigger that opens nothing. The registrar adds the wrapper attributes (an
 * anchor, an extra class) to the list item (spec 136, DECISIONS #670).
 */
final class MegaMenuRenderer implements BlockRenderer
{
    /** A caret pointing down; turned by the block's style when the panel is open. */
    private const CARET = '<svg class="corex-icon corex-mega-menu__caret" width="16" height="16" viewBox="0 0 24 24"'
        . ' fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"'
        . ' aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>';

    public function __construct(private readonly MegaMenuPanels $panels)
    {
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function render(array $attributes, string $content, object $block): string
    {
        $label = trim(wp_strip_all_tags((string) ($attributes['label'] ?? '')));
        $slug = sanitize_title((string) ($attributes['panel'] ?? ''));

        if ($label === '' || $slug === '') {
            return '';
        }

        $panel = $this->panels->render($slug);
        if ($panel === '') {
            return '';
        }

        $id = wp_unique_id('corex-mega-menu-');
        $triggerId = $id . '-trigger';
        $panelId = $id . '-panel';

        return sprintf(
            '<li class="wp-block-navigation-item corex-mega-menu" data-corex-mega-menu>'
            . '<button type="button" id="%1$s" class="wp-block-navigation-item__content corex-nav__trigger corex-mega-menu__trigger" aria-expanded="false" aria-controls="%2$s">'
            . '<span class="wp-block-navigation-item__label corex-mega-menu__label">%3$s</span>%4$s</button>'
            . '<div id="%2$s" class="corex-nav__panel" role="group" aria-labelledby="%1$s" hidden>%5$s</div>'
            . '</li>',
            esc_attr($triggerId),
            esc_attr($panelId),
            esc_html($label),
            self::CARET,
            $panel,
        );
    }
}
