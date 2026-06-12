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
 * Checks the running PHP version against the minimum Corex supports. Below it is critical — the
 * framework's typed, 8.3-era code will not run reliably on an older runtime.
 */
final class PhpVersionProbe implements HealthProbe
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
            'php_version',
            __('PHP version', 'corex'),
            $ok
                ? sprintf(__('PHP %1$s meets the Corex minimum (%2$s).', 'corex'), $this->current, $this->minimum)
                : sprintf(__('PHP %1$s is below the Corex minimum of %2$s.', 'corex'), $this->current, $this->minimum),
            $ok ? [] : [__('Ask your host to upgrade PHP to 8.3 or newer.', 'corex')],
        );
    }
}
