<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

use Corex\Config\Branding\BrandingService;
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

        wp_enqueue_media(); // the media frame the picker opens
        wp_enqueue_script(
            'corex-settings',
            plugins_url('assets/settings.js', dirname(__DIR__, 2) . '/corex-config.php'),
            ['media-views'],
            '1.0.0',
            true,
        );
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

        // The form HTML is built with per-value escaping in SettingsForm.
        echo $this->form->render(fn (string $key): string => $this->store->get($key), $nonce)
            . '</div>';
    }

    private function renderHeader(): void
    {
        $logo = $this->branding->logoUrl();

        echo '<h1 class="wp-heading-inline">';
        if ($logo !== '') {
            // height is an HTML attribute (the admin-bar-scale logo size), not an inline style.
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
