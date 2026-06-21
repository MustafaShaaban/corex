<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

use Corex\Config\Branding\BrandingService;
use Corex\Config\ControlPanel\ControlPanelView;
use Corex\Config\Dashboard\SiteStatusCardRenderer;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Corex admin area: a top-level menu + a server-rendered settings screen. Saving
 * verifies the nonce + capability (via the shared AdminGuard), sanitizes each declared
 * field, and persists it to the option the Config engine reads. Image settings use the
 * WordPress media picker (spec 032); the configured logo shows in the screen header so the
 * branding is findable.
 */
final class AdminDashboard
{
    private string $hook = '';

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsForm $form,
        private readonly SettingsStore $store,
        private readonly AdminGuard $guard,
        private readonly BrandingService $branding,
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
            __('Corex', 'corex'),
            __('Corex', 'corex'),
            'manage_options',
            'corex-settings',
            [$this, 'render'],
            'dashicons-screenoptions',
            58
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $base = dirname(__DIR__, 2) . '/corex-config.php';

        wp_enqueue_media(); // the media frame the picker opens
        wp_enqueue_script(
            'corex-settings',
            plugins_url('assets/settings.js', $base),
            ['media-views'],
            '1.0.0',
            true,
        );

        // The control-panel card/checklist styling (spec 044) — only on this screen (Principle VI).
        wp_enqueue_style('corex-control-panel', plugins_url('assets/control-panel.css', $base), ['corex-admin-tokens'], '1.0.0');
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            return;
        }

        $nonce = wp_nonce_field('corex_settings', 'corex_settings_nonce', true, false);

        echo '<div class="wrap">';
        $this->renderHeader();

        // A live "Site status" card — what the enabled add-ons actually did + where the data is (spec 042).
        $this->status->render();

        // The control panel: an onboarding checklist + one status card per domain (spec 044).
        // The cards/checklist are already escaped by ControlPanelView.
        echo $this->panel->render($this->settingValues());

        // The form HTML is built with per-value escaping in SettingsForm. The per-section
        // state makes each section reflect its add-on's runtime state (spec 060 / M6 US2).
        echo $this->form->render(
            fn (string $key): string => $this->store->get($key),
            $nonce,
            fn (string $sectionKey): ?SettingsSectionState => $this->sectionState($sectionKey),
        ) . '</div>';
    }

    /**
     * The runtime state of a settings section so the form can reflect it (spec 060 / M6 US2).
     * Captcha: not installed → hidden; installed-inactive → disabled; active but the site
     * key/secret are not both set → configuration needed; active + configured → normal.
     * Other sections have no add-on gating (null = normal).
     */
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

    /**
     * The current settings values (Config dot-key => value) the control panel derives
     * its per-domain status from.
     *
     * @return array<string,mixed>
     */
    private function settingValues(): array
    {
        $values = [];

        foreach ($this->registry->keys() as $key) {
            $values[$key] = $this->store->get($key);
        }

        return $values;
    }

    private function renderHeader(): void
    {
        $logo = $this->branding->logoUrl();

        echo '<h1 class="wp-heading-inline">';
        if ($logo !== '') {
            // Decorative usage (logo-manifest.json): the adjacent "Corex Settings" heading
            // already names the product, so the mark carries an empty alt to avoid a
            // duplicate announcement. height is an HTML attribute (the admin-bar-scale logo
            // size), not an inline style.
            printf('<img src="%s" alt="" height="32" class="corex-brand-logo" /> ', esc_url($logo));
        }
        echo esc_html__('Corex Settings', 'corex') . '</h1>';
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

            // Write-only secrets render empty, so an empty submit means "keep the saved
            // value" — never overwrite a stored secret with a blank (spec 060 / M6 US2).
            if ($value === '' && in_array($key, $secretKeys, true)) {
                continue;
            }

            $this->store->save($key, $value);
        }
    }

    /**
     * Password-typed (write-only secret) keys, derived from the registry field types.
     *
     * @return list<string>
     */
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
