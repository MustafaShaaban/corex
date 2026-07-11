<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

use Corex\Admin\AdminPage;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Corex → Insights admin screen: a submenu that mounts two result cards (Performance,
 * Readiness), each with a "Run check" button. Renders + gates (shared AdminGuard); enqueues a
 * small vanilla script + token-only styles only on its own screen, and hands the script the REST
 * root, a nonce, and the provider list. All data comes from the cap+nonce-gated InsightsController.
 */
final class InsightsScreen
{
    private string $hook = '';

    public function __construct(
        private readonly InsightRegistry $registry,
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
            __('CoreX Insights', 'corex'),
            __('Insights', 'corex'),
            'manage_options',
            'corex-insights',
            [$this, 'render'],
            60,
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('insights');

            return;
        }

        echo $this->page->open(
            'insights',
            __('Readiness & Insights', 'corex'),
            __('Run performance and release-readiness checks. Environment-gated results remain explicitly gated.', 'corex'),
        ) . '<div id="corex-insights-app" class="corex-insights"></div>' . $this->page->close();
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $base = dirname(__DIR__, 2) . '/corex-config.php';

        wp_enqueue_style('corex-insights', plugins_url('assets/insights.css', $base), ['corex-admin-shell'], '1.1.0');
        // Depends on the shared runtime (spec 043): the script talks to the envelope through
        // window.Corex.api, and corex-runtime brings wp-i18n.
        wp_enqueue_script('corex-insights', plugins_url('assets/insights.js', $base), ['corex-runtime'], '1.1.0', true);

        wp_localize_script('corex-insights', 'corexInsights', [
            'restUrl'   => esc_url_raw(rest_url('corex/v1/insights')),
            'nonce'     => wp_create_nonce('wp_rest'),
            'providers' => array_map(
                static fn (InsightProvider $p): array => ['id' => $p->id(), 'label' => $p->label()],
                $this->registry->all(),
            ),
        ]);
    }
}
