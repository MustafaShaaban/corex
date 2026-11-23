<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

/**
 * Where a mega menu item's panel comes from (spec 137, FR-010, FR-012; DECISIONS #691). Bound in the
 * container, so the renderer is tested without WordPress and a site could draw panels another way.
 */
interface MegaMenuPanels
{
    /**
     * The panel with this slug, drawn for the page.
     *
     * @param string $slug The panel's template part slug.
     * @return string Its markup; empty when there is no such panel, it holds nothing, or it is already
     *                being drawn (a panel that holds the menu it belongs to).
     */
    public function render(string $slug): string;
}
