<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Config\Data\DataSourceService;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/** Mounts the REST-backed model, import, export, and migration workspace. */
final class DataModelsScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly DataSourceService $sources,
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
            __('CoreX Data Models', 'corex'),
            __('Data Models', 'corex'),
            CorexAbility::MANAGE_DATA_MODELS,
            'corex-data-models',
            [$this, 'render'],
            31,
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized(CorexAbility::MANAGE_DATA_MODELS)) {
            echo wp_kses_post($this->page->permissionDenied('data-models'));

            return;
        }

        echo wp_kses_post($this->page->open(
            'data-models',
            __('CoreX Data Models', 'corex'),
            __('Inspect schemas and run capability-backed record, import, export, and migration workflows.', 'corex'),
        ) . '<div id="corex-data-models-app"></div>' . $this->page->close());
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
            'corex-data-models',
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
        wp_enqueue_style(
            'corex-data-models',
            plugins_url('assets/data-models.css', $base . '/corex-config.php'),
            ['corex-data'],
            $asset['version'],
        );
        wp_localize_script('corex-data-models', 'corexDataModels', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/data')),
            'nonce' => wp_create_nonce('wp_rest'),
            'sources' => $this->sources->catalog(get_current_user_id()),
        ]);
        wp_set_script_translations('corex-data-models', 'corex');
    }
}
