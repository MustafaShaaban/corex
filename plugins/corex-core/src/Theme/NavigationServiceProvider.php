<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

use Corex\Foundation\ServiceProvider;

defined('ABSPATH') || exit;

/**
 * Registers the M3 navigation/footer presentation surface (Spec 058):
 *
 * - the `corex` block-pattern category, so site owners can find CoreX header/footer
 *   variants in the inserter (the variant patterns themselves are auto-registered by
 *   WordPress from `theme/patterns/*.php` for block themes — no PHP per pattern); and
 * - the `corex-navigation` stylesheet, attached to the core navigation block via
 *   {@see wp_enqueue_block_style()} so it loads only where a navigation renders
 *   (constitution Principle VI) and never as a global library.
 *
 * The provider holds no business logic, no routes, and no DB access; the markup and
 * styles live in the theme (Principle I). It registers on `init`, independent of any
 * theme being active, and no-ops cleanly when the CoreX theme (which owns the asset)
 * is not present.
 */
final class NavigationServiceProvider extends ServiceProvider
{
    private const STYLE_HANDLE = 'corex-navigation';

    private const STYLE_RELATIVE_PATH = 'assets/css/corex-navigation.css';

    public function register(): void
    {
        // No services to bind; presentation-only surface.
    }

    public function boot(): void
    {
        add_action('init', [$this, 'registerPatternCategory']);
        add_action('init', [$this, 'registerNavigationStyle']);
    }

    public function registerPatternCategory(): void
    {
        if (! function_exists('register_block_pattern_category')) {
            return;
        }

        register_block_pattern_category(
            'corex',
            ['label' => __('CoreX', 'corex')],
        );
    }

    public function registerNavigationStyle(): void
    {
        if (! function_exists('wp_enqueue_block_style')) {
            return;
        }

        $path = get_theme_file_path(self::STYLE_RELATIVE_PATH);

        // The stylesheet ships with the CoreX theme; when another theme is active the
        // CoreX patterns do not exist, so attaching the style would be a dead 404.
        if (! is_file($path)) {
            return;
        }

        $args = [
            'handle' => self::STYLE_HANDLE,
            'src'    => get_theme_file_uri(self::STYLE_RELATIVE_PATH),
            'path'   => $path,
            'ver'    => COREX_CORE_VERSION,
        ];

        // Attach to the blocks every CoreX header/footer reliably contains: the core
        // navigation block (header) and the corex/copyright block (footer legal row).
        // The shared handle is registered once and loads only where one of them renders.
        foreach (['core/navigation', 'corex/copyright'] as $blockName) {
            wp_enqueue_block_style($blockName, $args);
        }
    }
}
