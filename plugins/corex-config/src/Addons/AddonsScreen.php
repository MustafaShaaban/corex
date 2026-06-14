<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

use Corex\Provisioning\KitProvisioner;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The "Corex Add-ons" screen: a submenu under the Corex menu that lists every Corex add-on
 * with its state and lets an admin enable/disable each (plugin + feature flag together),
 * dependency-aware. The decisions are the pure AddonRegistry/AddonManager; this screen only
 * renders, gates (via the shared AdminGuard), and delegates the writes to the activator —
 * the same shape as the settings + setup-wizard screens.
 */
final class AddonsScreen
{
    public function __construct(
        private readonly AddonRegistry $registry,
        private readonly AddonManager $manager,
        private readonly AddonActivator $activator,
        private readonly AdminGuard $guard,
        private readonly KitProvisioner $provisioner,
        private readonly PendingKits $pending,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybeToggle']);
    }

    public function menu(): void
    {
        add_submenu_page(
            'corex-settings',
            __('Corex Add-ons', 'corex'),
            __('Add-ons', 'corex'),
            'manage_options',
            'corex-addons',
            [$this, 'render'],
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            return;
        }

        $state = $this->state();

        echo '<div class="wrap"><h1>' . esc_html__('Corex Add-ons', 'corex') . '</h1>';
        echo '<p>' . esc_html__('Enable or disable each Corex add-on. Dependencies are enforced.', 'corex') . '</p>';

        foreach ($this->manager->views($state, [$this, 'isInstalled']) as $view) {
            $this->renderRow($view);
        }

        echo '</div>';
    }

    private function renderRow(AddonView $view): void
    {
        echo '<div class="card">';
        echo '<h2>' . esc_html($view->addon->label) . '</h2>';
        echo '<p><strong>' . esc_html__('Status:', 'corex') . '</strong> ' . esc_html($this->statusLabel($view)) . '</p>';

        $this->renderManifest($view->addon);

        if ($view->addon->hasFlag()) {
            $flag = $view->flagOn ? __('on', 'corex') : __('off', 'corex');
            echo '<p><strong>' . esc_html__('Feature flag:', 'corex') . '</strong> ' . esc_html($flag) . '</p>';
        }

        if (! $view->installed) {
            echo '</div>';

            return;
        }

        if ($view->isBlocked()) {
            echo '<p><em>' . esc_html((string) $view->blockedReason) . '</em></p></div>';

            return;
        }

        $this->renderToggleForm($view);
        echo '</div>';
    }

    /**
     * The rich manifest (spec 044, US4): what the add-on does, what it registers, what it
     * requires/needs configured, and a docs link — so an admin understands it before toggling.
     */
    private function renderManifest(Addon $addon): void
    {
        if ($addon->summary !== '') {
            echo '<p>' . esc_html($addon->summary) . '</p>';
        }
        if ($addon->description !== '') {
            echo '<p class="description">' . esc_html($addon->description) . '</p>';
        }

        if ($addon->provides !== []) {
            echo '<p><strong>' . esc_html__('Registers:', 'corex') . '</strong></p><ul class="ul-disc">';
            foreach ($addon->provides as $item) {
                echo '<li>' . esc_html($item) . '</li>';
            }
            echo '</ul><p class="description">'
                . esc_html__('Enabling registers the above; disabling removes them.', 'corex') . '</p>';
        }

        if ($addon->requires !== []) {
            echo '<p><strong>' . esc_html__('Requires:', 'corex') . '</strong> '
                . esc_html(implode(', ', $addon->requires)) . '</p>';
        }

        if ($addon->needsConfiguration()) {
            echo '<p><strong>' . esc_html__('Needs configuration:', 'corex') . '</strong> '
                . esc_html(implode(', ', $addon->needsKeys)) . '</p>';
        }

        if ($addon->docsUrl !== '') {
            echo '<p><a href="' . esc_url($addon->docsUrl) . '">'
                . esc_html__('Documentation', 'corex') . '</a></p>';
        }
    }

    private function renderToggleForm(AddonView $view): void
    {
        $action = $view->active ? 'disable' : 'enable';
        $label  = $view->active ? __('Disable', 'corex') : __('Enable', 'corex');

        echo '<form method="post">';
        echo wp_nonce_field('corex_addons', 'corex_addons_nonce', true, false);
        echo '<input type="hidden" name="corex_addon" value="' . esc_attr($view->addon->slug) . '" />';
        echo '<input type="hidden" name="corex_addon_action" value="' . esc_attr($action) . '" />';
        echo '<button type="submit" class="button button-primary">' . esc_html($label) . '</button>';
        echo '</form>';
    }

    public function maybeToggle(): void
    {
        if (! isset($_POST['corex_addon'], $_POST['corex_addon_action'])) {
            return;
        }

        if (! $this->guard->verifiedPost('corex_addons_nonce', 'corex_addons')) {
            return;
        }

        $slug   = sanitize_key(wp_unslash($_POST['corex_addon']));
        $action = sanitize_key(wp_unslash($_POST['corex_addon_action']));
        $addon  = $this->registry->find($slug);

        if ($addon === null) {
            $this->notice(__('Unknown add-on.', 'corex'), 'error');

            return;
        }

        $action === 'enable'
            ? $this->tryEnable($addon)
            : $this->tryDisable($addon);
    }

    private function tryEnable(Addon $addon): void
    {
        $state = $this->state();

        if (! $this->manager->canEnable($addon->slug, $state)) {
            $missing = implode(', ', $this->manager->missingDependencies($addon->slug, $state));
            $this->notice(sprintf(/* translators: %s: dependency list */ __('Enable its dependency first: %s', 'corex'), $missing), 'error');

            return;
        }

        $this->activator->enable($addon);
        $this->notice(sprintf(/* translators: %s: add-on label */ __('Enabled %s.', 'corex'), $addon->label), 'success');

        // If the enabled add-on is a kit, queue its activation prompt (spec 042) — content is never applied
        // automatically; the prompt lets the admin choose to apply the starter content.
        $kit = $this->provisioner->kitForModule($addon->slug);

        if ($kit !== null) {
            $this->pending->add($kit);
        }
    }

    private function tryDisable(Addon $addon): void
    {
        $state = $this->state();

        if (! $this->manager->canDisable($addon->slug, $state)) {
            $dependents = implode(', ', $this->manager->blockingDependents($addon->slug, $state));
            $this->notice(sprintf(/* translators: %s: dependent add-on list */ __('Still required by: %s', 'corex'), $dependents), 'error');

            return;
        }

        $this->activator->disable($addon);
        $this->notice(sprintf(/* translators: %s: add-on label */ __('Disabled %s.', 'corex'), $addon->label), 'success');
    }

    /**
     * Whether an add-on's plugin file is present on disk.
     */
    public function isInstalled(string $slug): bool
    {
        $addon = $this->registry->find($slug);

        return $addon !== null && file_exists(WP_PLUGIN_DIR . '/' . $addon->pluginFile);
    }

    private function state(): AddonState
    {
        /** @var list<string> $active */
        $active = (array) get_option('active_plugins', []);

        $activeSlugs = [];
        foreach ($this->registry->all() as $addon) {
            if (in_array($addon->pluginFile, $active, true)) {
                $activeSlugs[] = $addon->slug;
            }
        }

        $enabledFlags = [];
        foreach ($this->registry->all() as $addon) {
            if ($addon->hasFlag() && get_option('corex_features_' . $addon->flag) === '1') {
                $enabledFlags[] = (string) $addon->flag;
            }
        }

        return new AddonState($activeSlugs, $enabledFlags);
    }

    private function statusLabel(AddonView $view): string
    {
        if (! $view->installed) {
            return __('Not installed', 'corex');
        }

        return $view->active ? __('Active', 'corex') : __('Inactive', 'corex');
    }

    private function notice(string $message, string $type): void
    {
        add_action('admin_notices', static function () use ($message, $type): void {
            printf(
                '<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
                esc_attr($type),
                esc_html($message),
            );
        });
    }
}
