<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health\Probes;

defined('ABSPATH') || exit;

use Corex\Health\HealthProbe;
use Corex\Health\HealthStatus;
use Corex\Health\ProbeResult;

/**
 * Checks the WordPress version against the minimum Corex supports (FSE + the modern block + REST
 * surface). Below it is critical — the framework relies on APIs that older cores lack.
 */
final class WpVersionProbe implements HealthProbe
{
    public function __construct(
        private readonly string $current,
        private readonly string $minimum,
    ) {
    }

    public function run(): ProbeResult
    {
        $ok = version_compare($this->current, $this->minimum, '>=');

        return new ProbeResult(
            $ok ? HealthStatus::Good : HealthStatus::Critical,
            'wp_version',
            __('WordPress version', 'corex'),
            $ok
                ? sprintf(__('WordPress %1$s meets the Corex minimum (%2$s).', 'corex'), $this->current, $this->minimum)
                : sprintf(__('WordPress %1$s is below the Corex minimum of %2$s.', 'corex'), $this->current, $this->minimum),
            $ok ? [] : [__('Update WordPress to 7.0 or newer.', 'corex')],
        );
    }
}
