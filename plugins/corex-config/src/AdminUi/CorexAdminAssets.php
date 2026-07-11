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
     * prefix WordPress derives (the toplevel `toplevel_page_corex-settings` Overview, and every
     * submenu — `corex_page_*` or `corex-framework_page_*` depending on how WP sanitises the
     * "COREX FRAMEWORK" menu title — plus any declarative `corex-page-*` option page). Every CoreX
     * submenu lives under the `corex-settings` parent, so its hook/screen id ends in
     * `_page_corex-<slug>`; matching that prefix covers ALL current and future CoreX screens
     * (Overview, Add-ons, Data, Data Models, Forms, Submissions, Email Studio, Operations &
     * Security, Access, Insights, Setup, Settings, option pages) so the full-bleed shell body class
     * is never missing on a real CoreX screen. Keyed on the slug after `_page_`, so the same check
     * works for both the enqueue hook and the `get_current_screen()` id (which disagree for the
     * submenu pages).
     */
    private const SCREEN_PATTERN = '#(?:^toplevel_page_corex-settings$|_page_corex-[a-z0-9-]+)#';

    public function register(): void
    {
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_filter('admin_body_class', [$this, 'bodyClass']);
    }

    /**
     * Tags CoreX-owned admin screens with a body class so the shell stylesheet can make the
     * surface full-bleed (remove wp-admin's outer padding/margins) on those pages only — never
     * on unrelated wp-admin pages or the front end. A pinned Light/Dark appearance is mirrored
     * as `corex-appearance-*` (like the login) so the body-level token paint of the residual
     * canvas matches the shell instead of following the OS scheme (spec 067 F).
     */
    public function bodyClass(string $classes): string
    {
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;

        if ($screen !== null && $this->supports((string) $screen->id)) {
            $classes = trim($classes . ' corex-admin-screen');

            $appearance = (string) apply_filters('corex_admin_appearance', 'system');
            if (in_array($appearance, ['light', 'dark'], true)) {
                $classes .= ' corex-appearance-' . $appearance;
            }
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
