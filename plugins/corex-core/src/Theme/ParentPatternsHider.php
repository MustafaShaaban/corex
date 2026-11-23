<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

use WP_Block_Patterns_Registry;

defined('ABSPATH') || exit;

/**
 * Unregisters the patterns of the CoreX theme's `patterns/` folder from a child's editor, when the
 * child sets `hidePatterns` (spec 135, FR-021; DECISIONS #664).
 *
 * WordPress registers both themes' folders on `init` at 10 (`_register_theme_block_patterns()`), the
 * child's first, and skips a parent pattern whose slug the child's folder already has. The CoreX
 * theme's patterns are known by the `Slug:` headers of its own folder, never by a prefix (`corex-ui`
 * registers `corex/*` patterns too, and they stay), and a slug the child's folder declares is the
 * child's and stays.
 *
 * Only where the editor reads patterns: an admin screen (`init` at 11) and a REST request
 * (`rest_api_init`, which comes after `init`). A visitor's page is drawn with the registry whole,
 * because a CoreX template the child still uses inserts some of these patterns by slug.
 */
final class ParentPatternsHider
{
    public function __construct(private readonly ChildThemeSwitches $switches)
    {
    }

    /** `init` at 11: an admin screen. */
    public function hideInAdmin(): void
    {
        if (is_admin()) {
            $this->hide();
        }
    }

    /** `rest_api_init`, or a direct call: unregister them now. */
    public function hide(): void
    {
        if (! $this->switches->hides(ChildThemeSwitches::PATTERNS)) {
            return;
        }

        $child = wp_get_theme();
        $parent = $child->parent();
        if ($parent === false) {
            return;
        }

        $registry = WP_Block_Patterns_Registry::get_instance();
        $own = self::slugs($child->get_block_patterns());

        foreach (self::slugs($parent->get_block_patterns()) as $slug) {
            if (! in_array($slug, $own, true) && $registry->is_registered($slug)) {
                $registry->unregister($slug);
            }
        }
    }

    /**
     * The slugs of a theme folder's patterns, as `WP_Theme::get_block_patterns()` reads them.
     *
     * @param array<string, array<string, mixed>> $patterns
     * @return list<string>
     */
    public static function slugs(array $patterns): array
    {
        return array_values(array_filter(
            array_map(static fn (array $pattern): mixed => $pattern['slug'] ?? null, $patterns),
            'is_string',
        ));
    }
}
