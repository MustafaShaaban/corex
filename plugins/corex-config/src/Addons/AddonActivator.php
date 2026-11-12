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
 *
 * Each answers what WordPress did (spec 114, US-SAFE-08). The flag follows the plugin only
 * when the plugin moved: an activation that failed writes no flag, and a deactivation that left
 * the plugin running deletes none. Deactivation is for this site only; an add-on active for the
 * whole network is the network administrator's, and {@see AddonToggleService} never asks.
 */
final class AddonActivator
{
    public function enable(Addon $addon): AddonToggleResult
    {
        $this->ensurePluginApi();
        $activated = activate_plugins($addon->pluginFile);

        if (is_wp_error($activated)) {
            // A plugin that printed while it was activated is answered as an error, yet WordPress
            // has already added it to the active list. The add-on stays off, as the screen says.
            if (is_plugin_active($addon->pluginFile)) {
                deactivate_plugins($addon->pluginFile, true, false);
            }

            return AddonToggleResult::failed($this->reason($activated));
        }

        if (! is_plugin_active($addon->pluginFile)) {
            return AddonToggleResult::failed(__('WordPress did not activate the plugin.', 'corex'));
        }

        if ($addon->hasFlag()) {
            update_option('corex_features_' . $addon->flag, '1');
        }

        return AddonToggleResult::succeeded();
    }

    public function disable(Addon $addon): AddonToggleResult
    {
        $this->ensurePluginApi();
        deactivate_plugins($addon->pluginFile, false, false);

        if (is_plugin_active($addon->pluginFile)) {
            return AddonToggleResult::failed(__('WordPress did not deactivate the plugin.', 'corex'));
        }

        if ($addon->hasFlag()) {
            delete_option('corex_features_' . $addon->flag);
        }

        return AddonToggleResult::succeeded();
    }

    private function reason(\WP_Error $error): string
    {
        // activate_plugins() files each plugin's own error under the plugin's file name.
        $data = $error->get_error_data();
        foreach (is_array($data) ? $data : [] as $inner) {
            if ($inner instanceof \WP_Error && $inner->get_error_message() !== '') {
                return wp_strip_all_tags($inner->get_error_message());
            }
        }

        return wp_strip_all_tags($error->get_error_message());
    }

    private function ensurePluginApi(): void
    {
        if (! function_exists('activate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
    }
}
