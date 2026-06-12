<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Insights;

defined('ABSPATH') || exit;

/**
 * One insight check. A provider fetches its data (a remote audit, or local signals) for a URL and
 * returns a normalised {@see InsightResult}. `run()` never throws — an unconfigured, unreachable,
 * or malformed source returns a graceful `recommended` result (Principle IX), so the dashboard is
 * always renderable. The pure normalisation lives in the provider's normaliser/scorer.
 */
interface InsightProvider
{
    public function id(): string;

    public function label(): string;

    public function run(string $url): InsightResult;
}
