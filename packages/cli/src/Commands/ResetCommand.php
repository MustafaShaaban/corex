<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Commands;

defined('ABSPATH') || exit;

use Corex\Cli\Reset\ResetAction;
use Corex\Cli\Reset\ResetExecutor;
use Corex\Cli\Reset\ResetGate;
use Corex\Cli\Reset\ResetInventory;
use Corex\Cli\Reset\ResetPlan;
use Corex\Cli\Reset\ResetPlanner;
use Corex\Cli\Reset\ResetRequest;
use Corex\Multisite\ActivationScope;
use Corex\Multisite\MultisiteContext;
use Corex\Multisite\PluginActivationInspector;
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
        private readonly PluginActivationInspector $pluginActivationInspector,
        private readonly MultisiteContext $multisite,
    ) {
    }

    /**
     * @param array<int,string>    $args
     * @param array<string,string> $assoc
     *
     * ## OPTIONS
     *
     * [--network]
     * : Permit reset actions against network-activated add-ons. Site options remain site-scoped.
     */
    public function run(array $args, array $assoc): void
    {
        $request = new ResetRequest(
            mode: isset($assoc['hard']) ? ResetRequest::FULL : ResetRequest::SOFT,
            dryRun: isset($assoc['dry-run']),
            confirmed: isset($assoc['yes-i-mean-it']),
            network: isset($assoc['network']),
        );
        $networkGuardMessage = $this->networkGuardMessage($request);

        if ($networkGuardMessage !== null) {
            WP_CLI::error($networkGuardMessage);

            return;
        }

        $plan = $this->planner->plan($request, $this->gatherInventory($request->network));

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
            $networkWide = $action->kind === ResetAction::DEACTIVATE_ADDON
                && $this->pluginActivationInspector->scopeOf($action->target) === ActivationScope::Network;

            $this->executor->apply($action, $networkWide);
        }
    }

    private function gatherInventory(bool $network): ResetInventory
    {
        return new ResetInventory(
            addonPlugins: $this->activeAddons($network),
            optionKeys: $this->corexOptionKeys(),
            demoPageId: $this->demoPageId(),
            pageIds: $this->kitPageIds(),
        );
    }

    /**
     * The pages a kit seeded (tracked in `corex_kit_seeded_pages`) — removed by a soft reset
     * so exactly the kit content goes, never user content (spec 031).
     *
     * @return list<int>
     */
    private function kitPageIds(): array
    {
        return array_values(array_map('intval', (array) get_option('corex_kit_seeded_pages', [])));
    }

    /**
     * Active, resettable `corex-*` add-ons. Site activation is always in scope;
     * network activation requires the flag, and must-use plugins are immutable here.
     *
     * @return list<string>
     */
    private function activeAddons(bool $network): array
    {
        return array_values(array_filter(
            $this->pluginActivationInspector->activePluginFiles(),
            fn (string $file): bool => str_starts_with($file, 'corex-')
                && ! in_array(strtok($file, '/'), self::FRAMEWORK, true)
                && $this->resettableScope($file, $network),
        ));
    }

    private function hasNetworkActiveAddon(): bool
    {
        foreach ($this->pluginActivationInspector->activePluginFiles() as $pluginFile) {
            if (
                str_starts_with($pluginFile, 'corex-')
                && ! in_array(strtok($pluginFile, '/'), self::FRAMEWORK, true)
                && $this->pluginActivationInspector->scopeOf($pluginFile) === ActivationScope::Network
            ) {
                return true;
            }
        }

        return false;
    }

    private function networkGuardMessage(ResetRequest $request): ?string
    {
        $networkActiveAddon = $this->hasNetworkActiveAddon();

        if ($this->gate->permitsScope($request, $this->multisite->enabled(), $networkActiveAddon)) {
            return null;
        }

        if ($networkActiveAddon) {
            return __('Network-activated CoreX add-ons require the --network flag; no reset action was run.', 'corex');
        }

        return __('A full reset affects the whole Multisite database and requires the --network flag.', 'corex');
    }

    private function resettableScope(string $pluginFile, bool $network): bool
    {
        return match ($this->pluginActivationInspector->scopeOf($pluginFile)) {
            ActivationScope::Site => true,
            ActivationScope::Network => $network,
            default => false,
        };
    }

    /**
     * Every site-scoped `corex_*` option name. The `corex_network_*` namespace
     * is excluded even if a malformed install placed one in the site options table.
     *
     * @return list<string>
     */
    private function corexOptionKeys(): array
    {
        global $wpdb;

        /** @var list<string> $names */
        $names = $wpdb->get_col(
            $wpdb->prepare(
                'SELECT option_name FROM %i WHERE option_name LIKE %s AND option_name NOT LIKE %s',
                $wpdb->options,
                $wpdb->esc_like('corex_') . '%',
                $wpdb->esc_like('corex_network_') . '%',
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
