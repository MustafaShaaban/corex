<?php

/**
 * @package Corex\Ui
 */

declare(strict_types=1);

namespace Corex\Ui\Blocks;

defined('ABSPATH') || exit;

/**
 * The template part area that holds mega menu panels, `mega-menu` (spec 137, FR-010; DECISIONS #691).
 * Registered by the add-on, so it exists only where the mega menu item does; the Site Editor then
 * lists panels apart from headers and footers.
 */
final class MegaMenuArea
{
    public const AREA = 'mega-menu';

    public function register(): void
    {
        add_filter('default_wp_template_part_areas', [$this, 'add']);
    }

    /**
     * @param mixed $areas The areas WordPress allows, each `{area, label, description, icon, area_tag}`.
     * @return mixed The same list with the mega menu panels area last; anything else as it came.
     */
    public function add(mixed $areas): mixed
    {
        if (! is_array($areas)) {
            return $areas;
        }

        foreach ($areas as $area) {
            if (is_array($area) && ($area['area'] ?? null) === self::AREA) {
                return $areas;
            }
        }

        $areas[] = [
            'area'        => self::AREA,
            'label'       => _x('Mega menu panels', 'template part area', 'corex'),
            'description' => __('A panel opens under a mega menu item of a navigation menu. Each panel is chosen on its item.', 'corex'),
            'icon'        => 'layout',
            'area_tag'    => 'div',
        ];

        return $areas;
    }
}
