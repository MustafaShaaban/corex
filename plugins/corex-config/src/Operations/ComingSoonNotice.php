<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Config\AdminUi\ScreenAsset;
use WP_Admin_Bar;

/**
 * Tells somebody who is served the real site that visitors are not (spec 101, FR-016).
 *
 * The danger this exists for is a quiet one. An administrator in Coming soon sees their site
 * exactly as it will look after launch, on every page, for weeks — and nothing on it says the
 * public is being shown something else. The bar says so, on every front-end page, with the way to
 * see what a visitor sees; in the admin the same message is a toolbar node.
 *
 * It is a bar of its own on the front end because WordPress's toolbar can be switched off per
 * user there, and a notice that depends on a preference is not persistent. In the admin the
 * toolbar is always present, so the message goes in it.
 *
 * Who sees the bar is not decided here. {@see ComingSoonDecision} names the bar a response
 * carries, so "who is told" cannot drift from "who passes". It cannot be dismissed.
 */
final class ComingSoonNotice
{
    /** The stylesheet handle, enqueued only on a response that shows the bar (Principle VI). */
    public const STYLE = 'corex-coming-soon-bar';

    /** The toolbar node's id in the admin. */
    public const TOOLBAR_NODE = 'corex-coming-soon';

    private bool $printed = false;

    /** The decision for this request, asked for once: the stylesheet and the bar must agree. */
    private ?ComingSoonDecision $decision = null;

    public function __construct(
        private readonly ComingSoonGuard $guard,
        private readonly OperationsModeStore $store,
    ) {
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueue']);
        // After the WordPress toolbar, which prints at priority 0, so the bar sits beneath it.
        add_action('wp_body_open', [$this, 'render'], 1);
        // A theme that never fires `wp_body_open` still gets the bar, at the foot of the page.
        add_action('wp_footer', [$this, 'render'], 1);
        add_action('admin_bar_menu', [$this, 'addToolbarNode'], 90);
    }

    public function enqueue(): void
    {
        if ($this->decision()->bar === ComingSoonDecision::BAR_NONE) {
            return;
        }

        wp_enqueue_style(
            self::STYLE,
            plugins_url('assets/css/coming-soon-bar.css', COREX_CONFIG_FILE),
            // The bar's colours and sizes are CoreX's admin tokens, which are registered and
            // never enqueued globally: they arrive here, with the bar, and on no other response.
            ['corex-admin-tokens'],
            ScreenAsset::version(dirname(COREX_CONFIG_FILE) . '/assets/css/coming-soon-bar.css'),
        );
    }

    public function render(): void
    {
        if ($this->printed) {
            return;
        }

        $html = $this->html($this->decision(), current_user_can('manage_options'));
        if ($html === '') {
            return;
        }

        $this->printed = true;
        echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- html() escapes every value it places.
    }

    /**
     * The bar's markup for a decided response, or an empty string when the response carries none.
     *
     * @param bool $canChangeMode Whether to offer the screen that changes the mode. Only somebody
     *                            who can open it is sent there.
     */
    public function html(ComingSoonDecision $decision, bool $canChangeMode): string
    {
        if ($decision->bar !== ComingSoonDecision::BAR_NOTICE) {
            return '';
        }

        $links = '<li><a href="' . esc_url($this->visitorViewUrl()) . '">'
            . esc_html__('View the coming-soon page', 'corex') . '</a></li>';

        if ($canChangeMode) {
            $links .= '<li><a href="' . esc_url($this->operationsUrl()) . '">'
                . esc_html__('Open Operations & Security', 'corex') . '</a></li>';
        }

        // `corex-admin` is the class CoreX's tokens are defined on; the bar brings its own palette
        // rather than borrowing the theme's, so it reads the same on every client's site.
        return '<aside class="corex-admin corex-coming-soon-bar" aria-label="'
            . esc_attr__('Site status', 'corex') . '">'
            . '<p class="corex-coming-soon-bar__message"><strong>'
            . esc_html__('Coming soon is on.', 'corex') . '</strong> '
            . esc_html__('Visitors see the coming-soon page, not this site.', 'corex') . '</p>'
            . '<ul class="corex-coming-soon-bar__links">' . $links . '</ul>'
            . '</aside>';
    }

    /**
     * The same message in the admin, where the toolbar is always present.
     */
    public function addToolbarNode(WP_Admin_Bar $toolbar): void
    {
        // On the front end the bar above says it; saying it twice there is noise.
        if (! is_admin() || $this->store->current() !== OperationsMode::COMING_SOON) {
            return;
        }

        if (! current_user_can('edit_posts')) {
            return;
        }

        $toolbar->add_node([
            'id'    => self::TOOLBAR_NODE,
            'title' => __('Coming soon is on', 'corex'),
            'href'  => $this->visitorViewUrl(),
            'meta'  => ['title' => __('Visitors see the coming-soon page, not this site.', 'corex')],
        ]);
        $toolbar->add_node([
            'parent' => self::TOOLBAR_NODE,
            'id'     => self::TOOLBAR_NODE . '-view',
            'title'  => __('View the coming-soon page', 'corex'),
            'href'   => $this->visitorViewUrl(),
        ]);

        if (current_user_can('manage_options')) {
            $toolbar->add_node([
                'parent' => self::TOOLBAR_NODE,
                'id'     => self::TOOLBAR_NODE . '-operations',
                'title'  => __('Open Operations & Security', 'corex'),
                'href'   => $this->operationsUrl(),
            ]);
        }
    }

    private function decision(): ComingSoonDecision
    {
        return $this->decision ??= $this->guard->decision();
    }

    private function visitorViewUrl(): string
    {
        return add_query_arg([ComingSoonGuard::VISITOR_VIEW => '1'], home_url('/'));
    }

    private function operationsUrl(): string
    {
        return add_query_arg(
            ['page' => 'corex-operations-security', 'tab' => 'environment'],
            admin_url('admin.php'),
        );
    }
}
