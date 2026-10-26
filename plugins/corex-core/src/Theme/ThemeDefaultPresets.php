<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

use WP_Theme_JSON_Data;

defined('ABSPATH') || exit;

/**
 * Keeps WordPress's own font sizes and shadows off a page drawn by the CoreX theme (spec 121,
 * US-BRD-03, DECISIONS #462).
 *
 * The CoreX theme turns WordPress's defaults off in its theme.json (`defaultFontSizes`,
 * `shadow.defaultPresets`). WordPress then hides them from the editor and still prints them as
 * custom properties beside the theme's (`large`, `x-large`, `natural`, `deep` …), so a page carries
 * two scales. This removes them from WordPress's default layer, and only while the CoreX theme is
 * the active one: a client's own theme is never touched, whatever its theme.json says.
 */
final class ThemeDefaultPresets
{
    /** The CoreX theme's folder: the template of the CoreX theme or of a child of it. */
    public const THEME = 'corex';

    public function filter(WP_Theme_JSON_Data $defaults): WP_Theme_JSON_Data
    {
        if (get_template() !== self::THEME) {
            return $defaults;
        }

        return new WP_Theme_JSON_Data(self::without($defaults->get_data()), 'default');
    }

    /**
     * WordPress's default theme.json data without its font sizes and shadow presets.
     *
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public static function without(array $data): array
    {
        unset($data['settings']['typography']['fontSizes'], $data['settings']['shadow']['presets']);

        return $data;
    }
}
