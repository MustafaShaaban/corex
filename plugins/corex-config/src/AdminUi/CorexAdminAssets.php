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
