<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Forms;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;

/**
 * Guarded admin mount for the functional Forms & Flows client.
 */
final class FormsFlowsScreen
{
    private const FORMS_PLUGIN = 'corex-forms/corex-forms.php';

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
            __('CoreX Forms & Flows', 'corex'),
            __('Forms & Flows', 'corex'),
            'manage_options',
            'corex-forms',
            [$this, 'render'],
            25,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '' || ! $this->formsActive()) {
            return;
        }

        $base  = dirname(__DIR__, 2);
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];
        $dependencies   = array_map('strval', (array) ($asset['dependencies'] ?? []));
        $dependencies[] = 'corex-runtime';

        wp_enqueue_script(
            'corex-forms-builder',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            array_values(array_unique($dependencies)),
            (string) ($asset['version'] ?? 'dev'),
            true,
        );
        wp_enqueue_style(
            'corex-forms-builder',
            plugins_url('assets/forms-admin.css', $base . '/corex-config.php'),
            ['corex-admin-shell'],
            (string) ($asset['version'] ?? 'dev'),
        );
        wp_localize_script('corex-forms-builder', 'corexFlows', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/flows')),
            'nonce' => wp_create_nonce('wp_rest'),
            'ownerId' => get_current_user_id(),
        ]);
        wp_set_script_translations('corex-forms-builder', 'corex', $base . '/languages');
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('forms');

            return;
        }

        echo $this->page->open(
            'forms',
            __('CoreX Forms & Flows', 'corex'),
            __('Create, publish, preview, route, and test versioned visitor flows.', 'corex'),
        );
        if (! $this->formsActive()) {
            echo $this->page->state(
                'warning',
                __('CoreX Forms is not active', 'corex'),
                __('Activate the CoreX Forms plugin to use the visual flow builder.', 'corex'),
            );
            echo '<p><a class="button button-primary" href="' . esc_url(admin_url('admin.php?page=corex-addons')) . '">'
                . esc_html__('Open Add-ons', 'corex') . '</a></p>';
        } else {
            echo '<div id="corex-forms-flows-app" aria-live="polite"></div>';
        }
        echo $this->page->close();
    }

    private function formsActive(): bool
    {
        $active  = array_map('strval', (array) get_option('active_plugins', []));
        $network = array_keys((array) get_site_option('active_sitewide_plugins', []));

        return in_array(self::FORMS_PLUGIN, [...$active, ...$network], true);
    }
}
