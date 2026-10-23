<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

use Corex\Access\AbilityCheck;
use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Config\Access\ScreenAccess;
use Corex\Config\AdminUi\ScreenAsset;
use Corex\Config\Overview\OverviewRenderer;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\NetworkContext;
use Corex\Multisite\PluginActivationInspector;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * CoreX Overview and Settings screens. Writes remain on the shared AdminGuard.
 */
final class AdminDashboard
{
    /** How much of what was typed a refusal quotes. */
    private const QUOTED_AT_MOST = 80;

    private const OVERVIEW = 'corex-settings';
    private const SETTINGS = 'corex-settings-config';

    /** What changing how the whole site delivers mail needs (spec 117, FR-008). */
    private const SITE_ADMINISTRATION = 'manage_options';

    private string $hook = '';
    private string $settingsHook = '';

    /** True once a verified settings POST has been persisted this request, so the save toast shows. */
    private bool $justSaved = false;

    /**
     * The fields of that save that were not changed, because what was typed is not what the
     * field takes: each with its name in the form, its label, its type and what was typed.
     *
     * @var list<array{name:string,label:string,type:string,typed:string}>
     */
    private array $refused = [];

    public function __construct(
        private readonly SettingsRegistry $registry,
        private readonly SettingsForm $form,
        private readonly SettingsStore $store,
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly OverviewRenderer $overview,
        private readonly PluginActivationInspector $pluginActivationInspector,
        private readonly MultisiteContext $multisite,
        private readonly NetworkContext $network,
        private readonly SettingsSanitizer $sanitizer = new SettingsSanitizer(),
        private readonly ScreenAccess $access = new ScreenAccess(),
        private readonly AbilityCheck $abilities = new AbilityCheck(),
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
            $this->access->menuCapability(self::OVERVIEW),
            'corex-settings',
            [$this, 'render'],
            'dashicons-layout',
            58,
        );

        add_submenu_page(
            'corex-settings',
            __('CoreX Overview', 'corex'),
            __('Overview', 'corex'),
            $this->access->menuCapability(self::OVERVIEW),
            'corex-settings',
            [$this, 'render'],
            0,
        );

        $this->settingsHook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Settings', 'corex'),
            __('Settings', 'corex'),
            $this->access->menuCapability(self::SETTINGS),
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
            ['media-views'],
            ScreenAsset::version($dir . '/assets/settings.js'),
            true,
        );
    }

    public function render(): void
    {
        if (! $this->access->opens(self::OVERVIEW)) {
            echo $this->page->permissionDenied('overview');

            return;
        }

        echo $this->page->open(
            'overview',
            __('CoreX Overview', 'corex'),
            __('Framework health, onboarding progress, and the current operational state.', 'corex'),
            '',
            $this->access->readOnly(self::OVERVIEW),
        );

        // The Overview is one cohesive readiness dashboard (spec 064) — a single OverviewRenderer
        // produces the whole grid from real state, replacing the previously stacked site-status +
        // control-panel + activity panels (which duplicated read-outs and left white space).
        echo $this->overview->render($this->settingValues());
        echo $this->page->close();
    }

    public function renderSettings(): void
    {
        if (! $this->access->opens(self::SETTINGS)) {
            echo $this->page->permissionDenied('settings');

            return;
        }

        $readOnly = $this->access->readOnly(self::SETTINGS);
        // A read-only screen has nothing to send, so it carries no nonce to send it with.
        $nonce = $readOnly ? '' : wp_nonce_field('corex_settings', 'corex_settings_nonce', true, false);

        echo $this->page->open(
            'settings',
            __('CoreX Settings', 'corex'),
            __('Configure framework services. Secret values remain write-only.', 'corex'),
            '',
            $readOnly,
        );
        echo $this->form->render(
            fn (string $key): string => $this->settingDisplay($key),
            $nonce,
            fn (string $sectionKey): ?SettingsSectionState => $this->sectionState($sectionKey),
            $this->activeTab(),
            $this->guard->authorized(self::SITE_ADMINISTRATION),
        );

        if ($this->justSaved) {
            echo $this->refused === [] ? $this->savedToast() : $this->refusedToast();
        }

        echo $this->page->close();
    }

    /**
     * The value shown for a settings key. Most keys read the store; the Media "Server support" row is a
     * live read-out provided by the corex-media add-on through a filter (so this screen never hard-depends
     * on the optional add-on — Principle IX).
     */
    private function settingDisplay(string $key): string
    {
        if ($key === 'media.webp.support') {
            return (string) apply_filters('corex_media_support_summary', '');
        }

        // The Advanced section is a live, read-only system-diagnostics read-out — never stored.
        if (str_starts_with($key, 'advanced.')) {
            return $this->diagnosticValue($key);
        }

        $stored = (string) $this->store->get($key);

        // Reflect the Media defaults (conversion on; quality 82) before the first save, so the form
        // matches runtime (corex-media defaults these on when the option is unset). After any save the
        // stored value wins, including an explicit "off". Mirrors MediaSettings::defaults().
        if ($stored === '') {
            return match ($key) {
                'media.webp.enabled', 'media.webp.convert_jpeg', 'media.webp.convert_png' => '1',
                'media.webp.quality' => '82',
                'media.webp.min_saving' => '5',
                default => '',
            };
        }

        return $stored;
    }

    /**
     * The live value for one Advanced-section diagnostics row (spec 068 T203) — real runtime facts,
     * never a stored or fabricated value.
     */
    private function diagnosticValue(string $key): string
    {
        return match ($key) {
            'advanced.php_version'  => PHP_VERSION,
            'advanced.wp_version'   => (string) get_bloginfo('version'),
            'advanced.environment'  => (string) wp_get_environment_type(),
            'advanced.memory_limit' => (string) ini_get('memory_limit'),
            'advanced.multisite'    => $this->multisiteDiagnostic(),
            default                 => '',
        };
    }

    private function sectionState(string $sectionKey): ?SettingsSectionState
    {
        if ($sectionKey === 'media') {
            return $this->addonSectionState('corex-media/corex-media.php');
        }

        if ($sectionKey !== 'captcha') {
            return null;
        }

        if (! file_exists(WP_PLUGIN_DIR . '/corex-captcha/corex-captcha.php')) {
            return SettingsSectionState::Hidden;
        }

        $active = $this->pluginActivationInspector->isActive('corex-captcha/corex-captcha.php');

        if (! $active) {
            return SettingsSectionState::Disabled;
        }

        // None/Honeypot (or an unset driver) need no keys, so they are never "configuration
        // needed" — the section is in its normal, intentionally-off/keyless state.
        $driver = trim((string) $this->store->get('captcha.driver'));
        if ($driver === '' || $driver === 'none' || $driver === 'honeypot') {
            return SettingsSectionState::Normal;
        }

        $configured = trim((string) $this->store->get('captcha.site_key')) !== ''
            && trim((string) $this->store->get('captcha.secret')) !== '';

        return $configured ? SettingsSectionState::Normal : SettingsSectionState::ConfigurationNeeded;
    }

    /**
     * State for a section backed by an optional add-on: Hidden when its plugin file is absent,
     * Disabled when installed-but-inactive, otherwise Normal. Keeps an add-on's settings from
     * appearing usable before it is installed/active (mirrors the captcha pattern).
     */
    private function addonSectionState(string $pluginFile): SettingsSectionState
    {
        if (! file_exists(WP_PLUGIN_DIR . '/' . $pluginFile)) {
            return SettingsSectionState::Hidden;
        }

        $active = $this->pluginActivationInspector->isActive($pluginFile);

        return $active ? SettingsSectionState::Normal : SettingsSectionState::Disabled;
    }

    private function multisiteDiagnostic(): string
    {
        if (! $this->multisite->enabled()) {
            return __('No', 'corex');
        }

        $siteCount = $this->network->siteCount();
        /* translators: 1: WordPress network ID, 2: number of sites in the network. */
        $format = _n(
            'Yes — network %1$s, %2$s site',
            'Yes — network %1$s, %2$s sites',
            $siteCount,
            'corex',
        );

        return sprintf(
            $format,
            number_format_i18n($this->network->id()),
            number_format_i18n($siteCount),
        );
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
        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- only whether a save was sent; verified below.
        if (! isset($_POST['corex_settings_nonce'])) {
            return;
        }

        // A save from someone without "Manage settings", the holder of the view ability among them, is
        // refused with the standard refusal and recorded (spec 117, FR-005, FR-014).
        if (! $this->abilities->allows(CorexAbility::MANAGE_SETTINGS, self::SETTINGS)) {
            wp_die(
                esc_html__('You can read these settings but not change them.', 'corex'),
                esc_html__('Access denied', 'corex'),
                ['response' => 403],
            );
        }

        if (! $this->guard->verifiedPost('corex_settings_nonce', 'corex_settings', CorexAbility::MANAGE_SETTINGS)) {
            return;
        }

        // What an earlier save in this process refused is not this one's to report.
        $this->refused = [];
        // How the whole site delivers mail stays with site administration (spec 117, FR-008): those
        // fields are left as they are when anyone else saves.
        $siteAdministrator = $this->guard->authorized(self::SITE_ADMINISTRATION);
        foreach ($this->registry->sections() as $section) {
            foreach ($section['fields'] as $key => $field) {
                if (! $siteAdministrator && ! empty($field['site_admin'])) {
                    continue;
                }

                $this->saveField((string) $key, $field);
            }
        }

        $this->justSaved = true;
    }

    /**
     * The transient "Settings saved" toast (design: Blocks & Components, C / admin components).
     * Rendered once after a verified save; role="status" so assistive tech announces it, and it is
     * dismissible + auto-hides through the enqueued settings script. Token-only markup.
     */
    private function savedToast(): string
    {
        return sprintf(
            '<div class="corex-toast corex-toast--success" role="status" data-corex-toast>'
            . '<p class="corex-toast__message">%1$s</p>'
            . '<button type="button" class="corex-toast__dismiss" data-corex-toast-dismiss'
            . ' aria-label="%2$s">&times;</button>'
            . '</div>',
            esc_html__('Settings saved.', 'corex'),
            esc_attr__('Dismiss', 'corex'),
        );
    }

    /**
     * What a save says when a field was not changed (spec 106, story 5): which field, what was
     * typed, and why it was not taken. The rest was saved, and it says that too.
     *
     * It was said as "Settings saved": an address typed wrongly was dropped, and the field
     * came back as it had been with nothing to say why. An alert and not a status, since
     * something somebody asked for did not happen; it stays until it is dismissed.
     */
    private function refusedToast(): string
    {
        $lines = '';
        foreach ($this->refused as $field) {
            // The label and what was typed are each escaped where they stand. What was typed
            // keeps its own direction: in a right-to-left sentence "answers@" would otherwise
            // be drawn as "@answers".
            $lines .= sprintf(
                '<p class="corex-toast__message" id="corex-refused-%1$s" data-corex-refused="%1$s">%2$s</p>',
                esc_attr($field['name']),
                sprintf(
                    esc_html($this->whyRefused($field['type'])),
                    esc_html($field['label']),
                    '<bdi>' . esc_html(self::asTyped($field['typed'])) . '</bdi>',
                ),
            );
        }

        // It can be given focus, which the page's script does when it loads: an alert that
        // is already in a page as it arrives is not reliably read out, and it is the last
        // thing in the page.
        return sprintf(
            '<div class="corex-toast corex-toast--warning" role="alert" tabindex="-1" data-corex-toast data-corex-toast-stays>'
            . '<div class="corex-toast__messages">%1$s<p class="corex-toast__message">%2$s</p></div>'
            . '<button type="button" class="corex-toast__dismiss" data-corex-toast-dismiss'
            . ' aria-label="%3$s">&times;</button>'
            . '</div>',
            $lines,
            esc_html__('Everything else was saved.', 'corex'),
            esc_attr__('Dismiss', 'corex'),
        );
    }

    /**
     * What was typed, short enough to quote: its beginning, where it is long. A picture
     * pasted into a web address field as `data:` is thousands of characters.
     */
    private static function asTyped(string $typed): string
    {
        $text = trim(wp_check_invalid_utf8($typed, true));

        return mb_strlen($text) > self::QUOTED_AT_MOST ? mb_substr($text, 0, self::QUOTED_AT_MOST) . '…' : $text;
    }

    /** The sentence for a value a field of that type does not take. */
    private function whyRefused(string $type): string
    {
        return match ($type) {
            /* translators: 1: a setting's label. 2: what was typed into it. */
            'email' => __('%1$s was not changed: "%2$s" is not an email address.', 'corex'),
            /* translators: 1: a setting's label. 2: what was typed into it. */
            'url', 'media' => __('%1$s was not changed: "%2$s" is not a web address.', 'corex'),
            /* translators: 1: a setting's label. 2: what was typed into it. */
            'hex-colour' => __('%1$s was not changed: "%2$s" is not a six-digit colour such as #0B1F3B.', 'corex'),
            /* translators: 1: a setting's label. 2: what was chosen for it. */
            default => __('%1$s was not changed: "%2$s" is not one of its choices.', 'corex'),
        };
    }

    /**
     * Persists one field by type. A checkbox absent from the post is an explicit "off" (so a
     * toggle can be turned off); an empty write-only secret is left untouched (never cleared
     * by re-saving the form); everything else is sanitized text.
     */
    /** @param array{type?:string,label?:string,options?:array<string,string>} $field */
    private function saveField(string $key, array $field): void
    {
        $name = str_replace('.', '_', $key);
        $type = (string) ($field['type'] ?? 'text');

        if ($type === 'checkbox') {
            $this->store->save($key, isset($_POST[$name]) ? '1' : '0');

            return;
        }

        if (! isset($_POST[$name])) {
            return;
        }

        $raw = wp_unslash($_POST[$name]);
        if (! is_string($raw)) {
            return;
        }

        $value = $this->sanitizer->sanitize($raw, $type, $field['options'] ?? []);
        if ($value === null) {
            // Not saved, and said: what was there stays there.
            $this->refused[] = ['name' => $name, 'label' => (string) ($field['label'] ?? $key), 'type' => $type, 'typed' => $raw];

            return;
        }

        if ($this->sanitizer->shouldPreserve($value, $type)) {
            return;
        }

        $this->store->save($key, $value);
    }

    /**
     * The settings tab to show first: the one just saved (so the selection survives a save) or
     * a requested tab, validated against the real section keys. Read-only display preference,
     * so a sanitized key is sufficient (no state change).
     */
    private function activeTab(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only tab selection, not a state change.
        $requested = isset($_REQUEST['corex_tab']) ? sanitize_key(wp_unslash($_REQUEST['corex_tab'])) : '';

        return in_array($requested, array_keys($this->registry->sections()), true) ? $requested : '';
    }
}
