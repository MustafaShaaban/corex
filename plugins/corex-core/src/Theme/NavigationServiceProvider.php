<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Theme;

use Corex\Container\ContainerInterface;
use Corex\Foundation\ServiceProvider;

defined('ABSPATH') || exit;

/**
 * Registers the M3 navigation/footer presentation surface (Spec 058):
 *
 * - the `corex` block-pattern category, so site owners can find CoreX header/footer
 *   variants in the inserter (the variant patterns themselves are auto-registered by
 *   WordPress from `theme/patterns/*.php` for block themes, with no PHP per pattern);
 * - the `corex-navigation` stylesheet, attached to the core navigation block via
 *   {@see wp_enqueue_block_style()} so it loads only where a navigation renders
 *   (constitution Principle VI) and never as a global library; and
 * - the theme's navigation collapse rules at the width of its navigation breakpoint
 *   token, through {@see NavigationCollapseStyle} (spec 137, slice 1; DECISIONS #692).
 *
 * The provider holds no business logic, no routes, and no DB access; the markup and
 * styles live in the theme (Principle I). It binds the collapse classes in the
 * container and hooks the rest from `boot()`, independent of any theme being active:
 * the pattern category, the stylesheet and the script on `init`, the script and the
 * collapse rules on `render_block`, and the collapse rules on `enqueue_block_assets`.
 * The stylesheets and the script no-op cleanly when neither the active theme nor its
 * parent ships them.
 */
final class NavigationServiceProvider extends ServiceProvider
{
    private const STYLE_HANDLE = 'corex-navigation';

    private const STYLE_RELATIVE_PATH = 'assets/css/corex-navigation.css';

    private const SCRIPT_HANDLE = 'corex-navigation';

    private const SCRIPT_RELATIVE_PATH = 'assets/js/corex-navigation.js';

    public function register(): void
    {
        $this->container->singleton(
            NavigationBreakpoint::class,
            static fn (ContainerInterface $c): NavigationBreakpoint => new NavigationBreakpoint(),
        );

        $this->container->singleton(
            NavigationCollapseStyle::class,
            static fn (ContainerInterface $c): NavigationCollapseStyle => new NavigationCollapseStyle(
                $c->make(NavigationBreakpoint::class),
            ),
        );
    }

    public function boot(): void
    {
        add_action('init', [$this, 'registerPatternCategory']);
        add_action('init', [$this, 'registerNavigationStyle']);
        add_action('init', [$this, 'registerNavigationScript']);
        add_filter('render_block', [$this, 'maybeEnqueueNavigationScript'], 10, 2);

        $collapse = $this->container->make(NavigationCollapseStyle::class);
        add_filter('render_block', [$collapse, 'enqueueOnRender'], 10, 2);
        add_action('enqueue_block_assets', [$collapse, 'enqueueWhereEveryBlockLoads']);
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

    public function registerNavigationScript(): void
    {
        if (! function_exists('wp_register_script')) {
            return;
        }

        $path = get_theme_file_path(self::SCRIPT_RELATIVE_PATH);

        if (! is_file($path)) {
            return;
        }

        wp_register_script(
            self::SCRIPT_HANDLE,
            get_theme_file_uri(self::SCRIPT_RELATIVE_PATH),
            [],
            COREX_CORE_VERSION,
            true,
        );
    }

    /**
     * Enqueue the navigation behavior script only on requests that actually render a
     * CoreX header navigation or mega-menu (Principle VI), never globally. The markup
     * stays fully usable without it (core navigation + native <details> fallbacks).
     *
     * @param string               $content Rendered block HTML (returned unchanged).
     * @param array<string, mixed> $block   Parsed block.
     */
    public function maybeEnqueueNavigationScript(string $content, array $block): string
    {
        if (! function_exists('wp_enqueue_script') || ! wp_script_is(self::SCRIPT_HANDLE, 'registered')) {
            return $content;
        }

        $blockName = $block['blockName'] ?? '';
        $isNavigation = $blockName === 'core/navigation';
        $isMega = $blockName === 'core/details'
            && str_contains((string) ($block['attrs']['className'] ?? ''), 'corex-mega');

        if ($isNavigation || $isMega) {
            wp_enqueue_script(self::SCRIPT_HANDLE);
        }

        return $content;
    }
}
