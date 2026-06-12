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

        // The React uses the core DataViews component from the runtime `wp.dataviews` global;
        // declaring `wp-dataviews` ensures it is loaded where the WordPress version ships it.
        $deps = array_merge($asset['dependencies'], ['wp-dataviews']);

        wp_enqueue_script(
            'corex-data',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            $deps,
            $asset['version'],
            true,
        );

        wp_localize_script('corex-data', 'corexData', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/data')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'sources' => array_map(
                static fn (DataSource $s): array => ['key' => $s->key(), 'label' => $s->label()],
                $this->registry->all(),
            ),
        ]);
    }
}
