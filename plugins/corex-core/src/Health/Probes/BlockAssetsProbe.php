<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health\Probes;

use Corex\Health\AssetUrlHealth;
use Corex\Health\HealthProbe;
use Corex\Health\HealthStatus;
use Corex\Health\ProbeResult;

defined('ABSPATH') || exit;

/**
 * Surfaces the spec-040 failure (a block whose script/style URL embeds a filesystem path and 404s) so it stops
 * being silent: it judges a collected map of `corex/*` block asset URLs and reports Critical, naming the
 * offending blocks, when any URL is unhealthy — Good otherwise. Pure: the URL collection happens in the
 * HealthModule boundary; this only judges, so every case is unit-testable headlessly.
 */
final class BlockAssetsProbe implements HealthProbe
{
    /**
     * @param list<array{name:string,urls:list<string>}> $blocks
     */
    public function __construct(
        private readonly array $blocks,
        private readonly string $pluginsUrlBase,
        private readonly AssetUrlHealth $health = new AssetUrlHealth(),
    ) {
    }

    public function run(): ProbeResult
    {
        $broken = [];

        foreach ($this->blocks as $block) {
            foreach ($block['urls'] as $url) {
                if (! $this->health->isHealthy($url, $this->pluginsUrlBase)) {
                    $broken[] = $block['name'];

                    break;
                }
            }
        }

        $broken = array_values(array_unique($broken));

        if ($broken === []) {
            return new ProbeResult(
                HealthStatus::Good,
                'block_assets',
                __('Block assets', 'corex'),
                __('All Corex block scripts and styles resolve correctly.', 'corex'),
            );
        }

        return new ProbeResult(
            HealthStatus::Critical,
            'block_assets',
            __('Block assets', 'corex'),
            sprintf(
                /* translators: %s: comma-separated list of block names */
                __('These Corex blocks have unreachable script or style URLs: %s', 'corex'),
                implode(', ', $broken),
            ),
            [__('A plugin mount is producing filesystem-path URLs — re-link the plugin under wp-content/plugins.', 'corex')],
        );
    }
}
