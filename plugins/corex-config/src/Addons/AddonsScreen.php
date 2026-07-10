<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

use Corex\Admin\AdminPage;
use Corex\Config\Docs\DocsUrl;
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
        private readonly DocsUrl $docs,
        private readonly AddonCatalogService $catalog = new AddonCatalogService(),
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
            '1.1.0',
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
        $this->renderGuidance();

        echo '<div class="corex-addons__grid">';

        if ($views === []) {
            echo $this->page->state(
                'empty',
                __('No add-ons registered', 'corex'),
                __('Installed CoreX add-on packages will appear here.', 'corex'),
            );
        }

        foreach ($views as $view) {
            $this->renderRow($view, $state);
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
        $summary = $this->catalog->summary($views);

        echo '<div class="corex-addons__summary">';
        $this->renderSummaryStat(__('Active', 'corex'), (string) $summary['active'] . ' <span class="corex-addons__summary-total">/ ' . (int) $summary['total'] . '</span>');
        $this->renderSummaryStat(__('Updates', 'corex'), '<span class="corex-addons__summary-muted">' . esc_html__('not tracked', 'corex') . '</span>');
        $this->renderSummaryStat(__('Site kits', 'corex'), (string) $summary['siteKits']);
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
     * A short, honest orientation note above the grid: which plugins are the always-on framework
     * foundation (not toggleable add-ons, so they never appear as cards) and the reassurance that
     * a site does not need every add-on enabled to start. This is the admin-side mirror of the
     * "Required / recommended / optional add-ons" docs guidance.
     */
    private function renderGuidance(): void
    {
        echo '<div class="corex-addons__guidance corex-surface">'
            . '<p class="corex-addons__guidance-title">' . esc_html__('Where to start', 'corex') . '</p>'
            . '<p class="corex-addons__guidance-text">'
            . esc_html__(
                'The framework foundation — corex-core, corex-blocks, corex-config, corex-forms — is always active and is not listed here. You do not need to enable every add-on to start a site: enable Corex UI and the Company Kit for a typical company site, and add the rest only as a real need appears.',
                'corex',
            )
            . '</p></div>';
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

    /**
     * One add-on card (design: Add-ons & Data capture): a logo tile, the title + truthful
     * status badge, slug metadata, a short description, a cleanly-styled registers/requires
     * area, secondary doc links, and a designed accessible toggle in the action zone. When the
     * add-on cannot be toggled, the toggle is shown disabled with one honest reason line —
     * never a pasted warning strip.
     */
    private function renderRow(AddonView $view, AddonState $state): void
    {
        $status = $view->status();

        echo '<section class="corex-addon-card corex-surface">';
        echo '<header class="corex-addon-card__header">';
        echo '<span class="corex-addon-card__logo-tile"><img class="corex-addon-card__logo" src="'
            . esc_url($this->logoUrl($view))
            . '" width="48" height="48" alt="" aria-hidden="true" /></span>';
        echo '<div class="corex-addon-card__heading"><div class="corex-addon-card__title-row">'
            . '<h2>' . esc_html($view->addon->label) . '</h2>'
            . '<span class="screen-reader-text">' . esc_html__('Status:', 'corex') . '</span>'
            . '<span class="corex-badge corex-badge--' . esc_attr($status->tone()) . '">'
            . esc_html($this->statusLabel($status)) . '</span>'
            . $this->tierBadge($view->addon)
            . '</div>'
            . '<p class="corex-addon-card__slug">' . esc_html($view->addon->slug) . '</p></div>';
        echo '<div class="corex-addon-card__action">' . $this->toggleControl($view, $state) . '</div>';
        echo '</header>';

        $text = $view->addon->summary !== '' ? $view->addon->summary : $view->addon->description;
        if ($text !== '') {
            echo '<p class="corex-addon-card__desc">' . esc_html($text) . '</p>';
        }

        $this->renderMeta($view->addon);

        $reason = $this->unavailableReason($view, $state);
        if ($reason !== '') {
            echo '<p class="corex-addon-card__reason">'
                . '<span class="dashicons dashicons-info-outline" aria-hidden="true"></span>'
                . esc_html($reason) . '</p>';
        }

        echo '</section>';
    }

    /**
     * The cleanly-styled meta area: what the add-on registers (as chips), its dependencies, and
     * a documentation link — the information an admin needs before toggling, without clutter.
     */
    private function renderMeta(Addon $addon): void
    {
        if ($addon->provides !== []) {
            echo '<div class="corex-addon-card__registers"><p class="corex-addon-card__meta-label">'
                . esc_html__('Registers', 'corex') . '</p><ul class="corex-addon-card__chips">';
            foreach ($addon->provides as $item) {
                echo '<li>' . esc_html($item) . '</li>';
            }
            echo '</ul></div>';
        }

        $links = '';
        if ($addon->requires !== []) {
            $links .= '<span class="corex-addon-card__requires">' . esc_html__('Requires:', 'corex')
                . ' ' . esc_html(implode(', ', $addon->requires)) . '</span>';
        }
        if ($addon->docsUrl !== '') {
            // Resolve the relative docs path to an absolute URL so the link never points at the
            // active (client) WordPress domain; open in a new tab with safe rel attributes.
            $links .= '<a class="corex-addon-card__doc" href="' . esc_url($this->docs->resolve($addon->docsUrl)) . '"'
                . ' target="_blank" rel="noopener noreferrer">'
                . esc_html__('Documentation', 'corex')
                . ' <span aria-hidden="true">&#8599;</span>'
                . '<span class="screen-reader-text">' . esc_html__('(opens in a new tab)', 'corex') . '</span></a>';
        }
        if ($links !== '') {
            echo '<div class="corex-addon-card__links">' . $links . '</div>';
        }
    }

    /**
     * The advisory tier badge (Recommended / Optional / Site kit / Requires WooCommerce) that
     * tells a developer how the add-on relates to building a normal company site. Add-ons with
     * no tier (none currently) render nothing.
     */
    private function tierBadge(Addon $addon): string
    {
        if ($addon->tier === null) {
            return '';
        }

        return '<span class="corex-badge corex-badge--tier corex-badge--tier-' . esc_attr($addon->tier->value) . '">'
            . esc_html($addon->tier->label()) . '</span>';
    }

    /**
     * The action zone control. When the add-on is installed and the toggle would not break a
     * dependency, it is an accessible switch (`role="switch"` + `aria-checked`) that POSTs the
     * enable/disable through the existing guarded handler — no JS, keyboard-operable, with a
     * visible On/Off state (never colour alone). Otherwise it is a non-actionable disabled
     * switch shown for design parity; {@see unavailableReason()} renders the visible reason.
     */
    private function toggleControl(AddonView $view, AddonState $state): string
    {
        $slug = $view->addon->slug;
        $canDisable = $view->installed && $view->active && $this->manager->canDisable($slug, $state);
        $canEnable  = $view->installed && ! $view->active && $this->manager->canEnable($slug, $state);

        if (! $canDisable && ! $canEnable) {
            return $this->disabledToggle($view->active, $view->addon->label);
        }

        $action = $view->active ? 'disable' : 'enable';
        /* translators: %s: add-on name */
        $label = $view->active
            ? sprintf(__('Disable %s', 'corex'), $view->addon->label)
            : sprintf(__('Enable %s', 'corex'), $view->addon->label);

        return '<form method="post" class="corex-addon-card__toggle-form">'
            . wp_nonce_field('corex_addons', 'corex_addons_nonce', true, false)
            . '<input type="hidden" name="corex_addon" value="' . esc_attr($slug) . '" />'
            . '<input type="hidden" name="corex_addon_action" value="' . esc_attr($action) . '" />'
            . '<button type="submit" class="corex-toggle" role="switch" aria-checked="'
            . ($view->active ? 'true' : 'false') . '" aria-label="' . esc_attr($label) . '">'
            . $this->toggleInner($view->active)
            . '</button></form>';
    }

    private function disabledToggle(bool $on, string $label): string
    {
        /* translators: %s: add-on name */
        $aria = $on
            ? sprintf(__('%s is enabled', 'corex'), $label)
            : sprintf(__('%s is disabled', 'corex'), $label);

        return '<span class="corex-toggle corex-toggle--disabled" role="switch" aria-checked="'
            . ($on ? 'true' : 'false') . '" aria-disabled="true" aria-label="' . esc_attr($aria) . '">'
            . $this->toggleInner($on) . '</span>';
    }

    private function toggleInner(bool $on): string
    {
        return '<span class="corex-toggle__track" aria-hidden="true"><span class="corex-toggle__knob"></span></span>'
            . '<span class="corex-toggle__state">'
            . ($on ? esc_html__('On', 'corex') : esc_html__('Off', 'corex')) . '</span>';
    }

    /**
     * One honest line explaining why a non-actionable add-on cannot be toggled — empty when the
     * add-on is freely togglable. Never fabricates a reason.
     */
    private function unavailableReason(AddonView $view, AddonState $state): string
    {
        if (! $view->installed) {
            return __('Not installed — add the plugin package to enable it.', 'corex');
        }

        if ($view->isBlocked()) {
            return (string) $view->blockedReason;
        }

        if (! $view->active && ! $this->manager->canEnable($view->addon->slug, $state)) {
            $missing = implode(', ', $this->manager->missingDependencies($view->addon->slug, $state));

            /* translators: %s: dependency list */
            return sprintf(__('Enable its dependency first: %s', 'corex'), $missing);
        }

        return '';
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
