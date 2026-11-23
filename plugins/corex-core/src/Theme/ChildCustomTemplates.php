<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

use WP_Theme_JSON_Data;

defined('ABSPATH') || exit;

/**
 * Completes the custom templates a child of the CoreX theme declares, before WordPress merges them
 * with the CoreX theme's (spec 135, FR-023; DECISIONS #660, #664).
 *
 * WordPress merges the two `customTemplates` lists by position: the child's entry i is written over
 * the parent's entry i, key by key. A child that lists only its own template would so take the
 * place of CoreX's first one, and its title. On `wp_theme_json_data_theme`, which hands over the
 * child's own data before the parent's is merged in, the list becomes CoreX's entries in CoreX's
 * order (the child's own entry in place of a CoreX one it names), then the child's other entries.
 * CoreX's entries go first because each then lands on the parent's entry of the same name, and the
 * child's new ones land past the parent's list, where they take no key they omit from a CoreX entry.
 * A child that declares no list is left alone: it inherits CoreX's as before.
 */
final class ChildCustomTemplates
{
    public function __construct(private readonly ChildThemeSwitches $switches)
    {
    }

    public function filter(WP_Theme_JSON_Data $themeJson): WP_Theme_JSON_Data
    {
        // WordPress hands the parent's own file over as the child's when the child has none.
        if (! $this->switches->isCorexChild() || ! $this->switches->childHas('theme.json')) {
            return $themeJson;
        }

        $data = $themeJson->get_data();
        $own = $data['customTemplates'] ?? null;
        $corex = $this->switches->parentThemeJson()['customTemplates'] ?? null;

        if (! is_array($own) || ! is_array($corex)) {
            return $themeJson;
        }

        $data['customTemplates'] = self::complete($own, $corex);

        return new WP_Theme_JSON_Data($data, 'theme');
    }

    /**
     * CoreX's entries in CoreX's order, each replaced by the child's entry of the same name if it
     * has one, then the child's entries CoreX does not have, in the child's order.
     *
     * @param array<int, mixed> $own   The child's declared entries.
     * @param array<int, mixed> $corex The CoreX theme's entries.
     * @return array<int, mixed>
     */
    public static function complete(array $own, array $corex): array
    {
        $byName = [];
        foreach ($own as $entry) {
            $name = self::nameOf($entry);
            if ($name !== null && ! isset($byName[$name])) {
                $byName[$name] = $entry;
            }
        }

        $completed = [];
        $taken = [];
        foreach ($corex as $entry) {
            $name = self::nameOf($entry);
            $completed[] = $name !== null && isset($byName[$name]) ? $byName[$name] : $entry;
            if ($name !== null) {
                $taken[$name] = true;
            }
        }

        foreach ($own as $entry) {
            $name = self::nameOf($entry);
            if ($name === null || ! isset($taken[$name])) {
                $completed[] = $entry;
            }
        }

        return $completed;
    }

    private static function nameOf(mixed $entry): ?string
    {
        return is_array($entry) && is_string($entry['name'] ?? null) ? $entry['name'] : null;
    }
}
