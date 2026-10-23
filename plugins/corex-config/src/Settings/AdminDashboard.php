<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

use Corex\Admin\AdminPage;
use Corex\Config\AdminUi\ScreenAsset;
use Corex\Config\Overview\OverviewRenderer;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The CoreX Overview and Settings screens. Writes stay on the shared AdminGuard.
 *
 * Settings is a set of pages (spec 119): this class finds the page an address asks for, draws it
 * with its place in the rail, and saves it: verify, save that page, keep the result, redirect.
 */
final class AdminDashboard
{
    private string $hook = '';
    private string $settingsHook = '';

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsStore $store,
        private readonly SettingsPages $pages,
        private readonly SettingsPageRenderer $renderer,
        private readonly SettingsSaver $saver,
        private readonly SettingsSaveFlash $flash,
        private readonly SettingsSectionStates $states,
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly OverviewRenderer $overview,
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
            SettingsPages::SCREEN,
            [$this, 'renderSettings'],
            40,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if (! in_array($hook, [$this->hook, $this->settingsHook], true)) {
            return;
        }

        $dir  = dirname(__DIR__, 2);
        $base = $dir . '/corex-config.php';

        wp_enqueue_style(
            'corex-control-panel',
            plugins_url('assets/control-panel.css', $base),
            ['corex-admin-shell'],
            ScreenAsset::version($dir . '/assets/control-panel.css'),
        );

        if ($hook !== $this->settingsHook) {
            return;
        }

        wp_enqueue_media();
        wp_enqueue_script(
            'corex-settings',
            plugins_url('assets/settings.js', $base),
            ['media-views', 'corex-runtime'],
            ScreenAsset::version($dir . '/assets/settings.js'),
            true,
        );
        wp_localize_script('corex-settings', 'corexSettings', [
            'restUrl' => esc_url_raw(rest_url('corex/v1')),
            'nonce'   => wp_create_nonce('wp_rest'),
            'i18n'    => [
                'testing'    => __('Testing…', 'corex'),
                'testFailed' => __('The test request failed. Check your connection and try again.', 'corex'),
                'remove'     => __('Remove', 'corex'),
                'keep'       => __('Keep it', 'corex'),
                'removing'   => __('Removed when you save.', 'corex'),
                /* translators: %s: what stops working once the saved value is removed. */
                'confirm'    => __('Remove the saved value? %s', 'corex'),
            ],
        ]);
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

        // The Overview is one cohesive readiness dashboard (spec 064) built from real state.
        echo $this->overview->render($this->settingValues());
        echo $this->page->close();
    }

    public function renderSettings(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('settings');

            return;
        }

        $page = $this->requestedPage();

        // An address for a page that does not exist, or whose add-on is not installed.
        if ($page === null || $this->states->forPage($page) === SettingsSectionState::Hidden) {
            echo $this->page->open('settings', __('Settings', 'corex'), '', '', $this->subnav(''));
            echo $this->page->state(
                'info',
                __('This page is not available', 'corex'),
                __('It belongs to an add-on that is not installed on this site, or it does not exist. Choose another Settings page.', 'corex'),
            );
            echo $this->page->close();

            return;
        }

        echo $this->page->open('settings', $page->title, $page->description, $page->title, $this->subnav($page->key));
        echo $this->renderer->render(
            $page,
            $this->pages->url($page->key),
            wp_nonce_field('corex_settings', 'corex_settings_nonce', true, false),
            $this->flash->take(get_current_user_id(), $page->key),
        );
        echo $this->page->close();
    }

    /**
     * Saves the page that was posted, keeps what happened for the person who saved, and sends
     * the browser to the page, so a reload never sends the form again (spec 119, FR-006).
     */
    public function maybeSave(): void
    {
        if (! $this->guard->verifiedPost('corex_settings_nonce', 'corex_settings')) {
            return;
        }

        $pageKey = isset($_POST['corex_settings_page']) ? sanitize_key(wp_unslash($_POST['corex_settings_page'])) : '';
        $userId  = get_current_user_id();
        $result  = $this->saver->save($pageKey, wp_unslash($_POST), $userId);

        if ($result === null) {
            return;
        }

        $this->flash->keep($userId, $result);
        wp_safe_redirect($this->pages->url($result->page), 303);
        exit;
    }

    /** The page the address asks for. A read-only choice of what to show, so no nonce. */
    private function requestedPage(): ?SettingsPage
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- choosing which page to show.
        $page = isset($_GET['section']) ? sanitize_key(wp_unslash($_GET['section'])) : '';
        $tab  = isset($_GET['corex_tab']) ? sanitize_key(wp_unslash($_GET['corex_tab'])) : '';
        // phpcs:enable

        return $this->pages->requested($page, $tab);
    }

    /**
     * The Settings pages for the rail, without those whose add-on is not installed.
     *
     * @return list<array{label:string,url:string,current:bool}>
     */
    private function subnav(string $current): array
    {
        $links = [];
        foreach ($this->pages->all() as $page) {
            if ($this->states->forPage($page) === SettingsSectionState::Hidden) {
                continue;
            }

            $links[] = ['label' => $page->title, 'url' => $this->pages->url($page->key), 'current' => $page->key === $current];
        }

        return $links;
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
}
