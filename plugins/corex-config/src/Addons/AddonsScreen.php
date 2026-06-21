<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

use Corex\Admin\AdminPage;
use Corex\Foundation\AddonStatus;
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
        private readonly AdminPage $page,
    ) {
    }

    private string $hook = '';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'maybeToggle']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('Corex Add-ons', 'corex'),
            __('Add-ons', 'corex'),
            'manage_options',
            'corex-addons',
            [$this, 'render'],
            20,
        );
    }

    /**
     * The Add-ons screen styling — only on this screen (Principle VI), declaring the
     * scoped --corex-admin-* adapter as a dependency so it never restyles wp-admin
     * globally and never loads on the public frontend.
     */
    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-addons',
            plugins_url('assets/addons.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('addons');

            return;
        }

        $state = $this->state();

        echo $this->page->open(
            'addons',
            __('CoreX Add-ons', 'corex'),
            __('Manage installed add-ons only. Dependencies and runtime gates remain enforced.', 'corex'),
        );

        $views = $this->manager->views($state, [$this, 'isInstalled']);

        $this->renderSummary($views);

        echo '<div class="corex-addons__grid">';

        if ($views === []) {
            echo $this->page->state(
                'empty',
                __('No add-ons registered', 'corex'),
                __('Installed CoreX add-on packages will appear here.', 'corex'),
            );
        }

        foreach ($views as $view) {
            $this->renderRow($view);
        }

        echo '</div>' . $this->page->close();
    }

    /**
     * The truthful summary bar above the add-on grid (design: Add-ons & Data capture): how many
     * add-ons are active, how many are site kits, and the add-on philosophy. No update-checker
     * exists, so the Updates cell is shown by the design but reads "not tracked" — never a faked count.
     *
     * @param list<AddonView> $views
     */
    private function renderSummary(array $views): void
    {
        $total  = count($views);
        $active = 0;
        $kits   = 0;
        foreach ($views as $view) {
            if ($view->active) {
                $active++;
            }
            if (str_starts_with($view->addon->slug, 'corex-kit-')) {
                $kits++;
            }
        }

        echo '<div class="corex-addons__summary">';
        $this->renderSummaryStat(__('Active', 'corex'), (string) $active . ' <span class="corex-addons__summary-total">/ ' . (int) $total . '</span>');
        $this->renderSummaryStat(__('Updates', 'corex'), '<span class="corex-addons__summary-muted">' . esc_html__('not tracked', 'corex') . '</span>');
        $this->renderSummaryStat(__('Site kits', 'corex'), (string) $kits);
        echo '<div class="corex-addons__philosophy"><div><p class="corex-addons__philosophy-title">'
            . esc_html__('Add-ons self-disable', 'corex') . '</p><p class="corex-addons__philosophy-text">'
            . esc_html__('Never a hard dependency — toggle freely.', 'corex') . '</p></div>'
            . '<span class="corex-addons__philosophy-tag">' . esc_html__('safe', 'corex') . '</span></div>';
        echo '</div>';
    }

    private function renderSummaryStat(string $label, string $valueHtml): void
    {
        echo '<div class="corex-addons__summary-card"><p class="corex-addons__summary-label">'
            . esc_html($label) . '</p><p class="corex-addons__summary-value">'
            . wp_kses_post($valueHtml) . '</p></div>';
    }

    /**
     * The add-on's Module-Tile logo (design: Addon Logos — Final). Inactive/blocked/uninstalled
     * add-ons use the muted "disabled" master; unknown slugs fall back to the Core mark.
     */
    private function logoUrl(AddonView $view): string
    {
        $muted = ! $view->installed || $view->isBlocked() || in_array($view->status(), [
            AddonStatus::NotInstalled,
            AddonStatus::DependencyMissing,
            AddonStatus::WoocommerceMissing,
            AddonStatus::ProRequired,
        ], true);

        $suffix = $muted ? '--disabled' : '';
        $dir    = plugin_dir_path(COREX_CONFIG_FILE) . 'assets/addon-logos/';
        $file   = $view->addon->slug . $suffix . '.svg';

        if (! file_exists($dir . $file)) {
            $file = 'fallback' . $suffix . '.svg';
        }

        return plugins_url('assets/addon-logos/' . $file, COREX_CONFIG_FILE);
    }

    private function renderRow(AddonView $view): void
    {
        $status = $view->status();

        echo '<section class="corex-addon-card corex-surface">';
        echo '<header class="corex-addon-card__header">';
        echo '<img class="corex-addon-card__logo" src="' . esc_url($this->logoUrl($view))
            . '" width="48" height="48" alt="" aria-hidden="true" />';
        echo '<div class="corex-addon-card__heading"><div class="corex-addon-card__title-row">'
            . '<h2>' . esc_html($view->addon->label) . '</h2>'
            . '<span class="screen-reader-text">' . esc_html__('Status:', 'corex') . '</span>'
            . '<span class="corex-badge corex-badge--' . esc_attr($status->tone()) . '">'
            . esc_html($this->statusLabel($status)) . '</span></div>'
            . '<p class="corex-addon-card__slug">' . esc_html($view->addon->slug) . '</p></div>';
        echo '</header>';

        $this->renderManifest($view->addon);

        if ($view->addon->hasFlag()) {
            $flag = $view->flagOn ? __('on', 'corex') : __('off', 'corex');
            echo '<p><strong>' . esc_html__('Feature flag:', 'corex') . '</strong> ' . esc_html($flag) . '</p>';
        }

        if (! $view->installed) {
            echo '</section>';

            return;
        }

        if ($view->isBlocked()) {
            echo '<p class="corex-addon-card__gate"><strong>' . esc_html__('Unavailable:', 'corex') . '</strong> '
                . esc_html((string) $view->blockedReason) . '</p></section>';

            return;
        }

        $this->renderToggleForm($view);
        echo '</section>';
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

    private function statusLabel(AddonStatus $status): string
    {
        return match ($status) {
            AddonStatus::NotInstalled       => __('Not installed', 'corex'),
            AddonStatus::Inactive           => __('Inactive', 'corex'),
            AddonStatus::FeatureOff         => __('Feature off', 'corex'),
            AddonStatus::DependencyMissing  => __('Dependency missing', 'corex'),
            AddonStatus::WoocommerceMissing => __('WooCommerce missing', 'corex'),
            AddonStatus::ProRequired        => __('Pro required', 'corex'),
            AddonStatus::Active             => __('Active', 'corex'),
        };
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
