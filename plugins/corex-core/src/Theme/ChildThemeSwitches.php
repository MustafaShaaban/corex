<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

defined('ABSPATH') || exit;

/**
 * The switch a child of the CoreX theme sets to keep the CoreX theme's patterns out of its inserter
 * (spec 135, FR-021 and FR-024; DECISIONS #664, #668): `settings.custom.corex.parent.hidePatterns`
 * in the child's `theme.json`.
 *
 * It is read from the child's own file only. WordPress offers no theme JSON of the active theme
 * without its parent merged in, and when a child has no `theme.json` it hands the parent's file to
 * `wp_theme_json_data_theme` as the child's: so the file is read from the child's own folder, with
 * WordPress's own JSON reader, and nothing the CoreX theme's `theme.json` says can turn it on. The
 * switch is off unless it is the boolean `true`, and always off for the CoreX theme itself, a
 * standalone theme and a child of another theme.
 */
final class ChildThemeSwitches
{
    /** The CoreX theme's folder name, the `Template:` a child of it names. */
    public const THEME = 'corex';

    public const PATTERNS = 'hidePatterns';

    /** @var array<string, array<string, mixed>> The child's own theme.json, by its folder. */
    private array $own = [];

    public function isCorexChild(): bool
    {
        return get_template() === self::THEME && get_stylesheet() !== get_template();
    }

    public function hides(string $switch): bool
    {
        if (! $this->isCorexChild()) {
            return false;
        }

        $parent = $this->ownThemeJson()['settings']['custom']['corex']['parent'] ?? [];

        return is_array($parent) && ($parent[$switch] ?? false) === true;
    }

    /**
     * The child's own decoded theme.json, or [] when its folder holds none.
     *
     * @return array<string, mixed>
     */
    public function ownThemeJson(): array
    {
        $dir = get_stylesheet_directory();

        return $this->own[$dir] ??= self::decode($dir . '/theme.json');
    }

    /** Whether the child's own folder holds this theme-relative file. */
    public function childHas(string $relative): bool
    {
        return is_file(get_stylesheet_directory() . '/' . $relative);
    }

    /**
     * The CoreX theme's own decoded theme.json.
     *
     * @return array<string, mixed>
     */
    public function parentThemeJson(): array
    {
        return self::decode(get_template_directory() . '/theme.json');
    }

    /** @return array<string, mixed> */
    private static function decode(string $file): array
    {
        if (! is_readable($file)) {
            return [];
        }

        $decoded = wp_json_file_decode($file, ['associative' => true]);

        return is_array($decoded) ? $decoded : [];
    }
}
