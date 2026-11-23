<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

defined('ABSPATH') || exit;

/**
 * The breakpoint writer (spec 137, FR-002 to FR-006; DECISIONS #692): puts the theme's navigation
 * collapse rules on the page at the width of its navigation breakpoint token.
 *
 * Every rule of the theme's navigation styles that depends on the window's width is in one
 * stylesheet, written with the CoreX theme's width. A media condition cannot read a custom
 * property, so when the token is that width the file is served as it is, and when a theme or its
 * child sets another one the same rules are printed inline with the token's width in its place.
 * The file is found where WordPress finds a theme file, in the child first and then in its parent;
 * where neither ships it (a theme that is not the CoreX theme or built on it) nothing happens.
 *
 * The rules load where a Navigation block renders, as WordPress loads a block's own style on
 * demand, and wherever WordPress loads every block's style instead, the editor's canvas among
 * those (constitution VI). The token is read only then, never on a request that draws no menu.
 */
final class NavigationCollapseStyle
{
    public const HANDLE = 'corex-navigation-collapse';

    public const RELATIVE_PATH = 'assets/css/corex-navigation-collapse.css';

    public function __construct(private readonly NavigationBreakpoint $breakpoint)
    {
    }

    /**
     * `render_block`: a Navigation block on a page that loads block styles on demand brings the
     * rules with it.
     *
     * @param string               $content The block's markup, returned unchanged.
     * @param array<string, mixed> $block   The parsed block.
     */
    public function enqueueOnRender(string $content, array $block): string
    {
        if (($block['blockName'] ?? '') === 'core/navigation' && wp_should_load_block_assets_on_demand()) {
            $this->enqueue();
        }

        return $content;
    }

    /** `enqueue_block_assets`: where WordPress loads every block's style, it loads these rules too. */
    public function enqueueWhereEveryBlockLoads(): void
    {
        if (! wp_should_load_block_assets_on_demand()) {
            $this->enqueue();
        }
    }

    /**
     * Register the rules in the current style registry, once, and enqueue them. The editor builds
     * its canvas's styles in a registry of its own, so "once" is asked of the registry, not of
     * this object.
     */
    public function enqueue(): void
    {
        $path = get_theme_file_path(self::RELATIVE_PATH);

        if (! is_file($path)) {
            return;
        }

        if (! wp_style_is(self::HANDLE, 'registered')) {
            $this->register($path);
        }

        wp_enqueue_style(self::HANDLE);
    }

    private function register(string $path): void
    {
        $width = $this->breakpoint->width(wp_get_global_settings(['custom', 'nav', 'breakpoint']));

        if ($this->breakpoint->isDefault($width)) {
            wp_register_style(self::HANDLE, get_theme_file_uri(self::RELATIVE_PATH), [], COREX_CORE_VERSION);
            // As for any block style: WordPress may print a small file inline from its path.
            wp_style_add_data(self::HANDLE, 'path', $path);

            return;
        }

        wp_register_style(self::HANDLE, false, [], COREX_CORE_VERSION);
        wp_add_inline_style(
            self::HANDLE,
            str_replace(NavigationBreakpoint::DEFAULT, $width, (string) file_get_contents($path)),
        );
    }
}
