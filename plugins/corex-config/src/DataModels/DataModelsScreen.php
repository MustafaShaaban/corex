<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Config\AdminUi\ScreenAsset;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Forms\FlowFilterOptions;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The single home for data: models, records, import, export, and migrations.
 *
 * Records used to live here AND on a separate "Data" screen that rendered the identical explorer
 * from an identical config — two menu entries for one screen. The Data screen is gone and its
 * address redirects here.
 *
 * That consolidation forces the permissions to be honest. MANAGE_DATA and MANAGE_DATA_MODELS are
 * independent (CorexAbilityCatalog: neither implies the other; only MANAGE_ADMIN implies both), and
 * this screen gated on MANAGE_DATA_MODELS while the records explorer inside it reads sources gated
 * on MANAGE_DATA. So a models-only user already got an explorer that could read nothing, and gating
 * the survivor on models alone would have cut off data-only users entirely. The screen therefore
 * admits either ability and each tab asks for the one it actually needs — the same either-ability
 * rule DataRestGateway and DataManagementController already apply to the REST side.
 */
final class DataModelsScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly DataSourceService $sources,
        private readonly FlowFilterOptions $flows,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        // add_submenu_page takes one capability, so it gets the one that admits either holder; the
        // real gate is render(), and each tab is gated on the ability it needs.
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Data', 'corex'),
            __('Data', 'corex'),
            $this->menuCapability(),
            'corex-data-models',
            [$this, 'render'],
            30,
        );
    }

    public function render(): void
    {
        if (! $this->authorized()) {
            echo wp_kses_post($this->page->permissionDenied('data-models'));

            return;
        }

        echo wp_kses_post($this->page->open(
            'data-models',
            __('CoreX Data', 'corex'),
            __('Browse records and inspect schemas, then run capability-backed import, export, and migration workflows.', 'corex'),
        ) . '<div id="corex-data-models-app"></div>' . $this->page->close());
    }

    /** Either ability opens the screen; which tabs appear is decided per ability. */
    private function authorized(): bool
    {
        return $this->guard->authorized(CorexAbility::MANAGE_DATA)
            || $this->guard->authorized(CorexAbility::MANAGE_DATA_MODELS);
    }

    /**
     * The capability WordPress checks before drawing the menu entry.
     *
     * Whichever of the two this user holds — the entry must appear for either, and render() makes
     * the real decision.
     */
    private function menuCapability(): string
    {
        return current_user_can(CorexAbility::MANAGE_DATA_MODELS)
            ? CorexAbility::MANAGE_DATA_MODELS
            : CorexAbility::MANAGE_DATA;
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $base = dirname(__DIR__, 2);
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];
        $deps = [...$asset['dependencies'], 'corex-runtime'];

        wp_enqueue_script(
            'corex-data-models',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            $deps,
            $asset['version'],
            true,
        );
        wp_enqueue_style(
            'corex-data',
            plugins_url('assets/data.css', $base . '/corex-config.php'),
            ['corex-admin-shell'],
            ScreenAsset::version($base . '/assets/data.css'),
        );
        wp_enqueue_style(
            'corex-data-models',
            plugins_url('assets/data-models.css', $base . '/corex-config.php'),
            ['corex-data'],
            ScreenAsset::version($base . '/assets/data-models.css'),
        );
        wp_localize_script('corex-data-models', 'corexDataModels', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/data')),
            'nonce' => wp_create_nonce('wp_rest'),
            'sources' => $this->sources->catalog(get_current_user_id()),
            // Real form names for the records filter. NOTE: the explorer filters on the form SLUG
            // (meta corex_form_slug) while the submissions inbox filters on the flow ID
            // (meta corex_flow_id) — same list, different key.
            'flows' => $this->flows->all(),
            // Which tabs this user may open. Records needs `data` because that is what the sources
            // it reads are gated on; everything else reshapes the models themselves.
            'abilities' => [
                'data' => $this->guard->authorized(CorexAbility::MANAGE_DATA),
                'models' => $this->guard->authorized(CorexAbility::MANAGE_DATA_MODELS),
            ],
        ]);
        wp_set_script_translations('corex-data-models', 'corex');
    }
}
