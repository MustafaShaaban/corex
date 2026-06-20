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
        wp_enqueue_style('corex-control-panel', plugins_url('assets/control-panel.css', $base), [], '1.0.0');
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

        // The form HTML is built with per-value escaping in SettingsForm.
        echo $this->form->render(fn (string $key): string => $this->store->get($key), $nonce)
            . '</div>';
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

        foreach ($this->registry->keys() as $key) {
            $name = str_replace('.', '_', $key);

            if (isset($_POST[$name])) {
                $this->store->save($key, sanitize_text_field(wp_unslash($_POST[$name])));
            }
        }
    }
}
