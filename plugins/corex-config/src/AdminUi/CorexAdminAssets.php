<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\AdminUi;

defined('ABSPATH') || exit;

/**
 * Conditionally loads the shared shell on CoreX-owned admin screen hooks only.
 */
final class CorexAdminAssets
{
    /**
     * Matches every CoreX-owned admin screen by hook/screen-id, regardless of the menu-title
     * prefix WordPress derives (the toplevel `toplevel_page_corex-settings` Overview, and each
     * submenu — `corex_page_*` or `corex-framework_page_*` depending on how WP sanitises the
     * "COREX FRAMEWORK" menu title — plus any declarative `corex-page-*` option page). Keyed on
     * the page slug after `_page_`, so the same check works for both the enqueue hook and the
     * `get_current_screen()` id (which disagree for the submenu pages).
     */
    private const SCREEN_PATTERN = '#(?:^toplevel_page_corex-settings$|_page_corex-(?:settings-config|addons|data|insights|setup|page-))#';

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_filter('admin_body_class', [$this, 'bodyClass']);
    }

    /**
     * Tags CoreX-owned admin screens with a body class so the shell stylesheet can make the
     * surface full-bleed (remove wp-admin's outer padding/margins) on those pages only — never
     * on unrelated wp-admin pages or the front end.
     */
    public function bodyClass(string $classes): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ($screen !== null && $this->supports((string) $screen->id)) {
            $classes = trim($classes . ' corex-admin-screen');
        }

        return $classes;
    }

    public function supports(string $hook): bool
    {
        return preg_match(self::SCREEN_PATTERN, $hook) === 1;
    }

    public function enqueue(string $hook): void
    {
        if (! $this->supports($hook)) {
            return;
        }

        wp_enqueue_style('corex-admin-shell');
    }
}
