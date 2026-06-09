<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

/**
 * The Corex admin area: a top-level menu + a server-rendered settings screen. Saving
 * verifies the nonce + capability, sanitizes each declared field, and persists it to
 * the option the Config engine reads. The React/DataViews UI is the deferred upgrade.
 */
final class AdminDashboard
{
    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsForm $form,
        private readonly SettingsStore $store,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybeSave']);
    }

    public function menu(): void
    {
        add_menu_page(
            __('Corex', 'corex'),
            __('Corex', 'corex'),
            'manage_options',
            'corex-settings',
            [$this, 'render'],
            'dashicons-screenoptions',
            58
        );
    }

    public function render(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        $nonce = wp_nonce_field('corex_settings', 'corex_settings_nonce', true, false);

        // The form HTML is built with per-value escaping in SettingsForm.
        echo '<div class="wrap"><h1>' . esc_html__('Corex Settings', 'corex') . '</h1>'
            . $this->form->render(fn (string $key): string => $this->store->get($key), $nonce)
            . '</div>';
    }

    public function maybeSave(): void
    {
        if (! isset($_POST['corex_settings_nonce']) || ! current_user_can('manage_options')) {
            return;
        }

        if (wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['corex_settings_nonce'])), 'corex_settings') === false) {
            return;
        }

        foreach ($this->registry->keys() as $key) {
            $name = str_replace('.', '_', $key);

            if (isset($_POST[$name])) {
                $this->store->save($key, sanitize_text_field(wp_unslash($_POST[$name])));
            }
        }
    }
}
