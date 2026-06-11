<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Cli\Reset\ResetExecutor;
use Corex\Cli\Reset\ResetGate;
use Corex\Cli\Reset\ResetInventory;
use Corex\Cli\Reset\ResetPlan;
use Corex\Cli\Reset\ResetPlanner;
use Corex\Cli\Reset\ResetRequest;
use WP_CLI;

/**
 * The `wp corex reset` command — the thin WP-CLI boundary over the pure planner + gate.
 * It gathers the Corex footprint from WordPress, asks the planner for an ordered plan, and
 * either previews it (`--dry-run`), refuses a destructive plan that lacks the typed
 * safeguard (the gate, fail-closed), or executes it via the executor. The only class here
 * that talks to WP-CLI; all decisions live in the injected pure services (spec 025).
 */
final class ResetCommand
{
    /**
     * The framework plugins a soft reset keeps active (everything else `corex-*` is an
     * add-on and gets deactivated).
     *
     * @var list<string>
     */
    private const FRAMEWORK = ['corex-core', 'corex-blocks', 'corex-forms', 'corex-config'];

    public function __construct(
        private readonly ResetPlanner $planner,
        private readonly ResetGate $gate,
        private readonly ResetExecutor $executor,
    ) {
    }

    /**
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     */
    public function run(array $args, array $assoc): void
    {
        $request = new ResetRequest(
            mode: isset($assoc['hard']) ? ResetRequest::FULL : ResetRequest::SOFT,
            dryRun: isset($assoc['dry-run']),
            confirmed: isset($assoc['yes-i-mean-it']),
        );

        $plan = $this->planner->plan($request, $this->gatherInventory());

        if ($request->dryRun) {
            WP_CLI::log("Planned actions (dry run — nothing changed):\n" . $plan->summary());

            return;
        }

        if ($plan->isEmpty()) {
            WP_CLI::success('Nothing to reset — no Corex footprint found.');

            return;
        }

        if (! $this->gate->permits($request)) {
            WP_CLI::warning("Full reset refused — it would irreversibly WIPE the database.\nIt would:\n" . $plan->summary());
            WP_CLI::error('Re-run with --yes-i-mean-it (and --yes) to confirm you mean it.');

            return;
        }

        if ($plan->isDestructive()) {
            WP_CLI::confirm('This WIPES the database and restores a fresh Corex starter. Continue?', $assoc);
        }

        $this->execute($plan);

        WP_CLI::success("Reset complete:\n" . $plan->summary());
    }

    private function execute(ResetPlan $plan): void
    {
        foreach ($plan->actions as $action) {
            $this->executor->apply($action);
        }
    }

    private function gatherInventory(): ResetInventory
    {
        return new ResetInventory(
            addonPlugins: $this->activeAddons(),
            optionKeys: $this->corexOptionKeys(),
            demoPageId: $this->demoPageId(),
        );
    }

    /**
     * Active `corex-*` plugins that are add-ons (not framework plugins).
     *
     * @return list<string>
     */
    private function activeAddons(): array
    {
        /** @var list<string> $active */
        $active = (array) get_option('active_plugins', []);

        return array_values(array_filter(
            $active,
            static fn (string $file): bool => str_starts_with($file, 'corex-')
                && ! in_array(strtok($file, '/'), self::FRAMEWORK, true),
        ));
    }

    /**
     * Every `corex_*` option name (includes `corex_features_*` and `corex_setup_demo_seeded`).
     *
     * @return list<string>
     */
    private function corexOptionKeys(): array
    {
        global $wpdb;

        /** @var list<string> $names */
        $names = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
                $wpdb->esc_like('corex_') . '%',
            ),
        );

        return $names;
    }

    private function demoPageId(): ?int
    {
        if (get_option('corex_setup_demo_seeded') !== '1') {
            return null;
        }

        $pageId = (int) get_option('page_on_front');

        return $pageId > 0 ? $pageId : null;
    }
}
