<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * Applies an add-on toggle to WordPress — the side-effecting boundary, kept apart from the
 * pure registry/manager. Enabling activates the plugin and (where the add-on has one) turns
 * on its feature flag; disabling reverses both, so plugin activation and flag stay in sync.
 */
final class AddonActivator
{
    public function enable(Addon $addon): void
    {
        $this->ensurePluginApi();
        activate_plugins($addon->pluginFile);

        if ($addon->hasFlag()) {
            update_option('corex_features_' . $addon->flag, '1');
        }
    }

    public function disable(Addon $addon): void
    {
        $this->ensurePluginApi();
        deactivate_plugins($addon->pluginFile);

        if ($addon->hasFlag()) {
            delete_option('corex_features_' . $addon->flag);
        }
    }

    private function ensurePluginApi(): void
    {
        if (! function_exists('activate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
}
