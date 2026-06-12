<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Health;

defined('ABSPATH') || exit;

/**
 * The three outcomes of a health probe, ordered by severity. Mirrors WordPress Site Health's
 * own vocabulary (`good` / `recommended` / `critical`) so probes map cleanly onto that screen.
 */
enum HealthStatus: string
{
    case Good = 'good';
    case Recommended = 'recommended';
    case Critical = 'critical';

    /** Higher is worse — used to fold a set of results into the overall status. */
    public function severity(): int
    {
        return match ($this) {
            self::Good => 0,
            self::Recommended => 1,
            self::Critical => 2,
        };
    }
}
