<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Admin;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;

/**
 * CoreX → Assistant (spec 129, FR-014): one screen in the CoreX rail, drawn in the CoreX shell, where the
 * owner switches the assistant on, gives it its look, orders and keys its services, sets the booking
 * hours and the limits, and checks the services. Administrators only; the save goes through
 * {@see AdminGuard} with its own nonce.
 */
final class AssistantScreen
{
    public const SLUG = 'corex-assistant';
    public const SECTION = 'assistant';

    private const SAVE_ACTION = 'corex_assistant_save';
    private const NONCE_FIELD = 'corex_assistant_nonce';
    private const CAPABILITY = 'manage_options';

    private string $hook = '';

    public function __construct(
        private readonly AdminPage $page,
        private readonly AdminGuard $guard,
        private readonly AssistantScreenView $view,
        private readonly AssistantScreenSave $saver,
        private readonly string $pluginFile,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu'], 30);
        add_action('admin_enqueue_scripts', [$this, 'enqueue']);
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'save']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Assistant', 'corex'),
            __('Assistant', 'corex'),
            self::CAPABILITY,
            self::SLUG,
            [$this, 'render'],
            55,
        );
    }

    public function enqueue(string $hook): void
    {
        if ($this->hook === '' || $hook !== $this->hook) {
            return;
        }

        wp_enqueue_style('corex-admin-shell');
        $config = WP_PLUGIN_DIR . '/corex-config/corex-config.php';
        if (is_file($config)) {
            // The Settings screen's cards and rows, so this screen reads as one of them.
            wp_enqueue_style('corex-control-panel', plugins_url('assets/control-panel.css', $config), ['corex-admin-shell'], COREX_ASSISTANT_VERSION);
        }
        wp_enqueue_style('corex-assistant-admin', plugins_url('assets/assistant-admin.css', $this->pluginFile), ['corex-admin-shell'], COREX_ASSISTANT_VERSION);
        wp_enqueue_media();
        wp_enqueue_script('corex-assistant-admin', plugins_url('assets/assistant-admin.js', $this->pluginFile), ['wp-i18n', 'media-views'], COREX_ASSISTANT_VERSION, true);
        wp_set_script_translations('corex-assistant-admin', 'corex');
        wp_localize_script('corex-assistant-admin', 'corexAssistantAdmin', [
            'endpoint' => esc_url_raw(rest_url('corex/v1/assistant/check')),
            'nonce' => wp_create_nonce('wp_rest'),
        ]);
    }

    public function render(): void
    {
        if (! $this->guard->authorized(self::CAPABILITY)) {
            echo $this->page->permissionDenied(self::SECTION); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminPage escapes its markup.

            return;
        }

        echo $this->page->open( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- AdminPage escapes its markup.
            self::SECTION,
            __('Assistant', 'corex'),
            __('The chat on every public page: it answers from your site’s own content, through the services below in your order, and hands visitors to your team when it cannot help.', 'corex'),
        );

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the redirect's own flag, read to say "saved".
        if (isset($_GET['saved'])) {
            echo '<div class="corex-toast corex-toast--success" role="status"><p class="corex-toast__message">' . esc_html__('Settings saved.', 'corex') . '</p></div>';
        }

        printf(
            '<form method="post" action="%1$s" class="corex-assistant-screen">%2$s<input type="hidden" name="action" value="%3$s" />%4$s'
            . '<p class="corex-assistant-screen__save"><button type="submit" class="button button-primary">%5$s</button></p></form>',
            esc_url(admin_url('admin-post.php')),
            wp_nonce_field(self::SAVE_ACTION, self::NONCE_FIELD, true, false),
            esc_attr(self::SAVE_ACTION),
            $this->view->cards(), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the view escapes every value.
            esc_html__('Save changes', 'corex'),
        );

        echo $this->page->close(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- fixed markup.
    }

    public function save(): void
    {
        if (! $this->guard->verifiedPost(self::NONCE_FIELD, self::SAVE_ACTION, self::CAPABILITY)) {
            wp_die(esc_html__('This change could not be saved: the page was open too long, or you may not change these settings. Open the screen again and save from there.', 'corex'), '', ['response' => 403]);
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verified above by AdminGuard; each field is cleaned by the saver.
        $this->saver->save((array) wp_unslash($_POST));

        wp_safe_redirect(admin_url('admin.php?page=' . self::SLUG . '&saved=1'));
        exit;
    }
}
