<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

/**
 * Mega menu panels as template parts of the `mega-menu` area in the active theme (spec 137, FR-010,
 * FR-012; DECISIONS #691). A part is read with `get_block_template()`, so one saved in the Site Editor
 * wins over a theme's file, and drawn by WordPress's own template part block, so whatever translates
 * or filters a part does the same for a panel. Bound as one instance: it keeps the panels being drawn,
 * so a panel that holds the menu it belongs to is drawn once.
 */
final class TemplatePartMegaMenuPanels implements MegaMenuPanels
{
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

        return trim($markup) === '' ? '' : $markup;
    }

    private function isPanel(string $slug): bool
    {
        $part = get_block_template(get_stylesheet() . '//' . $slug, 'wp_template_part');

        return is_object($part)
            && ($part->area ?? null) === MegaMenuArea::AREA
            && has_blocks((string) ($part->content ?? ''));
    }
}
