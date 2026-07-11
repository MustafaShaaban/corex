<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/** Mounts the capability-derived Data management client on its guarded screen. */
final class DataAdminScreen
{
    private string $hook = '';

    public function __construct(
        private readonly DataSourceService $sources,
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
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
            CorexAbility::MANAGE_DATA,
            'corex-data',
            [$this, 'render'],
            30,
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized(CorexAbility::MANAGE_DATA)) {
            echo wp_kses_post($this->page->permissionDenied('data'));

            return;
        }

        echo wp_kses_post($this->page->open(
            'data',
            __('CoreX Data', 'corex'),
            __('Query and manage records through each registered source\'s declared capabilities.', 'corex'),
        ) . '<div id="corex-data-app"></div>' . $this->page->close());
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $base = dirname(__DIR__, 2);
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];
        $deps = [...$asset['dependencies'], 'corex-runtime'];

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
            ['corex-admin-shell'],
            $asset['version'],
        );
        wp_localize_script('corex-data', 'corexData', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/data')),
            'nonce' => wp_create_nonce('wp_rest'),
            'sources' => $this->sources->catalog(get_current_user_id()),
        ]);
        wp_set_script_translations('corex-data', 'corex');
    }
}
