<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

/**
 * One independent health check. Probes are small and self-contained — each reads the one thing it
 * judges (a PHP version, a writable path, an active theme) and returns a {@see ProbeResult}. WP
 * reads are injected or guarded so a probe stays unit-testable.
 */
interface HealthProbe
{
    public function run(): ProbeResult;
}
