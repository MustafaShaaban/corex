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
    /** @var list<string> */
    private const HOOKS = [
        'toplevel_page_corex-settings',
        'corex_page_corex-addons',
        'corex_page_corex-data',
        'corex_page_corex-settings-config',
        'corex_page_corex-setup',
        'corex_page_corex-insights',
    ];

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

        if ($screen !== null && $this->supports($screen->id)) {
            $classes .= ' corex-admin-screen';
        }

        return $classes;
    }

    public function supports(string $hook): bool
    {
        return in_array($hook, self::HOOKS, true)
            || str_starts_with($hook, 'corex_page_corex-page-')
            || str_starts_with($hook, 'toplevel_page_corex-page-');
    }

    public function enqueue(string $hook): void
    {
        if (! $this->supports($hook)) {
            return;
        }

        wp_enqueue_style('corex-admin-shell');
    }
}
