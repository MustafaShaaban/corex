<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Producers;

defined('ABSPATH') || exit;

/**
 * The launch checks whose "Readiness check not met" notification is raised (spec 118, US-OVR-01),
 * so the producer acts on a change of state and not on every evaluation. One option, not
 * autoloaded, written only when the set changes.
 */
class RaisedReadinessChecks
{
    public const OPTION = 'corex_readiness_raised';

    /** Whether anything was ever remembered: false on a site upgraded from before spec 118. */
    public function known(): bool
    {
        return is_array(get_option(self::OPTION, null));
    }

    /** @return list<string> */
    public function keys(): array
    {
        $stored = get_option(self::OPTION, []);

        return is_array($stored) ? array_values(array_map('strval', $stored)) : [];
    }

    /** @param list<string> $keys */
    public function remember(array $keys): void
    {
        update_option(self::OPTION, array_values(array_unique($keys)), false);
    }
}
