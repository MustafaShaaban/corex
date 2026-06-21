<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

use Corex\Admin\AdminPage;
use Corex\Config\ControlPanel\ControlPanelView;
use Corex\Config\Dashboard\SiteStatusCardRenderer;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * CoreX Overview and Settings screens. Writes remain on the shared AdminGuard.
 */
final class AdminDashboard
{
    private string $hook = '';
    private string $settingsHook = '';

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsForm $form,
        private readonly SettingsStore $store,
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly SiteStatusCardRenderer $status,
        private readonly ControlPanelView $panel,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybeSave']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_menu_page(
            __('CoreX Framework', 'corex'),
            __('COREX FRAMEWORK', 'corex'),
            'manage_options',
            'corex-settings',
            [$this, 'render'],
            'dashicons-layout',
            58,
        );

        add_submenu_page(
            'corex-settings',
            __('CoreX Overview', 'corex'),
            __('Overview', 'corex'),
            'manage_options',
            'corex-settings',
            [$this, 'render'],
            0,
        );

        $this->settingsHook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Settings', 'corex'),
            __('Settings', 'corex'),
            'manage_options',
            'corex-settings-config',
            [$this, 'renderSettings'],
            40,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if (! in_array($hook, [$this->hook, $this->settingsHook], true)) {
            return;
        }

        $base = dirname(__DIR__, 2) . '/corex-config.php';

        wp_enqueue_style(
            'corex-control-panel',
            plugins_url('assets/control-panel.css', $base),
            ['corex-admin-shell'],
            '1.0.0',
        );

        if ($hook !== $this->settingsHook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'corex-settings',
            plugins_url('assets/settings.js', $base),
            ['media-views'],
            '1.0.0',
            true,
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('overview');

            return;
        }

        echo $this->page->open(
            'overview',
            __('CoreX Overview', 'corex'),
            __('Framework health, onboarding progress, and the current operational state.', 'corex'),
        );

        $this->status->render();
        echo $this->panel->render($this->settingValues());
        echo $this->page->close();
    }

    public function renderSettings(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('settings');

            return;
        }

        $nonce = wp_nonce_field('corex_settings', 'corex_settings_nonce', true, false);

        echo $this->page->open(
            'settings',
            __('CoreX Settings', 'corex'),
            __('Configure framework services. Secret values remain write-only.', 'corex'),
        );
        echo $this->form->render(
            fn (string $key): string => $this->store->get($key),
            $nonce,
            fn (string $sectionKey): ?SettingsSectionState => $this->sectionState($sectionKey),
        );
        echo $this->page->close();
    }

    private function sectionState(string $sectionKey): ?SettingsSectionState
    {
        if ($sectionKey !== 'captcha') {
            return null;
        }

        if (! file_exists(WP_PLUGIN_DIR . '/corex-captcha/corex-captcha.php')) {
            return SettingsSectionState::Hidden;
        }

        $active = in_array(
            'corex-captcha/corex-captcha.php',
            array_map('strval', (array) get_option('active_plugins', [])),
            true,
        );

        if (! $active) {
            return SettingsSectionState::Disabled;
        }

        $configured = trim((string) $this->store->get('captcha.site_key')) !== ''
            && trim((string) $this->store->get('captcha.secret')) !== '';

        return $configured ? SettingsSectionState::Normal : SettingsSectionState::ConfigurationNeeded;
    }

    /** @return array<string,mixed> */
    private function settingValues(): array
    {
        $values = [];

        foreach ($this->registry->keys() as $key) {
            $values[$key] = $this->store->get($key);
        }

        return $values;
    }

    public function maybeSave(): void
    {
        if (! $this->guard->verifiedPost('corex_settings_nonce', 'corex_settings')) {
            return;
        }

        $secretKeys = $this->secretKeys();

        foreach ($this->registry->keys() as $key) {
            $name = str_replace('.', '_', $key);

            if (! isset($_POST[$name])) {
                continue;
            }

            $value = sanitize_text_field(wp_unslash($_POST[$name]));

            if ($value === '' && in_array($key, $secretKeys, true)) {
                continue;
            }

            $this->store->save($key, $value);
        }
    }

    /** @return list<string> */
    private function secretKeys(): array
    {
        $keys = [];

        foreach ($this->registry->sections() as $section) {
            foreach ($section['fields'] as $key => $field) {
                if (($field['type'] ?? '') === 'password') {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }
}
