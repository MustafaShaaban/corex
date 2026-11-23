<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

use WP_Block_Patterns_Registry;

defined('ABSPATH') || exit;

/**
 * Takes the patterns of the CoreX theme's `patterns/` folder out of a child's inserter, when the
 * child sets `hidePatterns` (spec 135, FR-021; DECISIONS #664, #668).
 *
 * WordPress registers both themes' folders on `init` at 10 (`_register_theme_block_patterns()`), the
 * child's first, and skips a parent pattern whose slug the child's folder already has. At 11, each
 * pattern the CoreX theme's folder declares is registered again under its own name with its own
 * properties and `inserter` set to `false`. It stays registered: a CoreX template that inserts one by
 * slug still draws it for a visitor and in the editor, while the block inserter and the Site Editor's
 * Patterns screen leave it out. The provider runs it where patterns are chosen, on an admin screen and
 * in a REST request (DECISIONS #668): a visitor's request leaves the registry as WordPress built it,
 * and the patterns are registered either way.
 *
 * The CoreX theme's patterns are known by the `Slug:` headers of its own folder, never by a prefix
 * (`corex-ui` registers `corex/*` patterns too, and they stay as they are), and a slug the child's
 * folder declares is the child's and stays as it is.
 */
final class ParentPatternsHider
{
    public function __construct(private readonly ChildThemeSwitches $switches)
    {
    }

    /** `init` at 11, after WordPress has registered the theme folders' patterns. */
    public function hideFromInserter(): void
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
            if (in_array($slug, $own, true)) {
                continue;
            }

            $pattern = $registry->get_registered($slug);
            if (is_array($pattern) && ($pattern['inserter'] ?? true) !== false) {
                $registry->register($slug, ['inserter' => false] + $pattern);
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
