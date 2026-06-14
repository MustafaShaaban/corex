<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Corex → Data admin screen: a submenu that mounts the React DataViews app for the
 * registered data sources. Renders + gates (shared AdminGuard); enqueues the built script
 * only on its own screen and hands it the REST root, a nonce, and the source list. The data
 * itself comes from the cap-gated DataController (spec 030).
 */
final class DataAdminScreen
{
    private string $hook = '';

    public function __construct(
        private readonly DataRegistry $registry,
        private readonly AdminGuard $guard,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('Corex Data', 'corex'),
            __('Data', 'corex'),
            'manage_options',
            'corex-data',
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            return;
        }

        echo '<div class="wrap"><h1>' . esc_html__('Corex Data', 'corex') . '</h1>'
            . '<div id="corex-data-app"></div></div>';
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $base  = dirname(__DIR__, 2); // corex-config plugin root
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];

        // The React uses the core DataViews component from the runtime `wp.dataviews` global.
        // Only declare `wp-dataviews` as a dependency when WordPress actually registers that
        // handle (newer cores) — otherwise enqueueing an unregistered dep emits a notice, and
        // the React already falls back to a plain table when the global is absent.
        $deps = $asset['dependencies'];
        // The shared runtime (spec 043): the app calls window.Corex.api for envelope-shaped data.
        $deps[] = 'corex-runtime';

        if (wp_script_is('wp-dataviews', 'registered')) {
            $deps[] = 'wp-dataviews';
        }

        wp_enqueue_script(
            'corex-data',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            $deps,
            $asset['version'],
            true,
        );

        wp_enqueue_style(
            'corex-data',
            plugins_url('assets/data.css', $base . '/corex-config.php'),
            [],
            $asset['version'],
        );

        wp_localize_script('corex-data', 'corexData', [
            'restUrl'     => esc_url_raw(rest_url('corex/v1/data')),
            'nonce'       => wp_create_nonce('wp_rest'),
            // The CSV export streams from the admin-post handler (DataExportController),
            // which re-checks this nonce + manage_options and bounds the row count.
            'exportUrl'   => esc_url_raw(admin_url('admin-post.php')),
            'exportNonce' => wp_create_nonce('corex_data_export'),
            'sources'     => array_map(
                static fn (DataSource $s): array => ['key' => $s->key(), 'label' => $s->label()],
                $this->registry->all(),
            ),
        ]);
    }
}
