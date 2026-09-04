<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config\Sources;

defined('ABSPATH') || exit;

use Corex\Support\Config\SettingScope;

/**
 * Its three-part guard — multisite enabled, matching scope, and a stored option — is what keeps the
 * new source non-breaking: unregistered keys remain site-scoped and follow today's chain (spec 100
 * FR-020–FR-024). It deliberately shares `corex_network_*` with network defaults so changing scope
 * never requires data migration.
 */
final class NetworkLockSource extends NetworkOptionSource
{
    protected function claimedScope(): SettingScope
    {
        return SettingScope::NetworkLocked;
    }
}
