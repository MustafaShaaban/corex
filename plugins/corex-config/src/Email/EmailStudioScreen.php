<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Email;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;

/**
 * Thin WordPress shell for the REST-backed functional Email Studio client.
 */
final class EmailStudioScreen
{
    private const EMAIL_PLUGIN = 'corex-email/corex-email.php';

    private string $hook = '';

    public function __construct(
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
            __('CoreX Email Studio', 'corex'),
            __('Email Studio', 'corex'),
            'manage_options',
            'corex-email-studio',
            [$this, 'render'],
            27,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '' || ! $this->emailActive()) {
            return;
        }

        $base  = dirname(__DIR__, 2);
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];
        $dependencies   = array_map('strval', (array) ($asset['dependencies'] ?? []));
        $dependencies[] = 'corex-runtime';

        wp_enqueue_script(
            'corex-email-studio',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            array_values(array_unique($dependencies)),
            (string) ($asset['version'] ?? 'dev'),
            true,
        );
        wp_enqueue_style(
            'corex-email-studio',
            plugins_url('assets/email-studio.css', $base . '/corex-config.php'),
            ['corex-admin-shell'],
            (string) ($asset['version'] ?? 'dev'),
        );
        wp_localize_script('corex-email-studio', 'corexEmailStudio', [
            'restUrl'     => esc_url_raw(rest_url('corex/v1/email-studio')),
            'nonce'       => wp_create_nonce('wp_rest'),
            'settingsUrl' => esc_url_raw(admin_url('admin.php?page=corex-settings-config&corex_tab=mail')),
        ]);
        wp_set_script_translations('corex-email-studio', 'corex', dirname(__DIR__, 2) . '/languages');
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('email');

            return;
        }

        echo $this->page->open(
            'email',
            __('CoreX Email Studio', 'corex'),
            __('Create, preview, route, test, inspect, and safely resend transactional email.', 'corex'),
        );

        if (! $this->emailActive()) {
            echo $this->page->state(
                'warning',
                __('CoreX Email is not active', 'corex'),
                __('Activate the CoreX Email add-on to use the functional Email Studio.', 'corex'),
            );
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=corex-addons')) . '">'
                . esc_html__('Open Add-ons', 'corex') . '</a></p>';
        } else {
            echo '<div id="corex-email-studio-app" aria-live="polite"></div>';
        }

        echo $this->page->close();
    }

    private function emailActive(): bool
    {
        $active = array_map('strval', (array) get_option('active_plugins', []));
        $network = array_keys((array) get_site_option('active_sitewide_plugins', []));

        return in_array(self::EMAIL_PLUGIN, [...$active, ...$network], true);
    }
}
