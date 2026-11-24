<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

/**
 * Mega menu panels as template parts of the `mega-menu` area in the active theme (spec 137, FR-010,
 * FR-012; DECISIONS #691, #705). A part is read with `get_block_template()`, so one saved in the Site
 * Editor wins over a theme's file, and drawn by WordPress's own template part block, so whatever
 * translates or filters a part does the same for a panel. Bound as one instance: it keeps the panels
 * being drawn, so a panel that holds the menu it belongs to is drawn once.
 */
final class TemplatePartMegaMenuPanels implements MegaMenuPanels
{
    /**
     * What shows on a page with no text in it: media, an icon, a control. A part that holds none of
     * these and no text shows nothing, whatever elements it draws.
     */
    private const SHOWS_WITHOUT_TEXT = '/<(?:img|svg|picture|video|audio|iframe|canvas|object|embed|input|select|textarea|button)\b/i';

    /** @var array<string,true> The slugs of the panels being drawn now. */
    private array $drawing = [];

    public function render(string $slug): string
    {
        if ($slug === '' || isset($this->drawing[$slug]) || ! $this->isPanel($slug)) {
            return '';
        }

        $this->drawing[$slug] = true;

        try {
            $markup = render_block([
                'blockName'    => 'core/template-part',
                'attrs'        => ['slug' => $slug, 'theme' => get_stylesheet(), 'tagName' => 'div'],
                'innerBlocks'  => [],
                'innerHTML'    => '',
                'innerContent' => [],
            ]);
        } finally {
            unset($this->drawing[$slug]);
        }

        return self::showsSomething($markup) ? $markup : '';
    }

    /**
     * A published part of the mega-menu area that holds a block. WordPress's part block draws only a
     * published part, and a draft or a trashed one would print its "unavailable" message.
     */
    private function isPanel(string $slug): bool
    {
        $part = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template_part');

        return is_object($part)
            && ($part->status ?? null) === 'publish'
            && ($part->area ?? null) === MegaMenuArea::AREA
            && has_blocks((string) ($part->content ?? ''));
    }

    /**
     * Whether drawn markup shows anything. WordPress always prints the part's wrapper, so the markup
     * is never empty; what counts is text, or an element that shows without text, inside it.
     */
    private static function showsSomething(string $markup): bool
    {
        $markup = (string) preg_replace('/<!--.*?-->/s', '', $markup);
        if (preg_match(self::SHOWS_WITHOUT_TEXT, $markup) === 1) {
            return true;
        }

        $text = html_entity_decode(strip_tags($markup), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim($text, " \t\n\r\0\x0B\u{00A0}") !== '';
    }
}
