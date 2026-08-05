<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * WordPress fixes the sole blog id at 1 outside multisite, making enumeration constant (spec FR-002).
 */
final class SingleSiteNetworkContext implements NetworkContext
{
    public function id(): int
    {
        return 1;
    }

    public function mainSiteId(): int
    {
        return 1;
    }

    public function siteCount(): int
    {
        return 1;
    }

    public function siteIds(int $limit = 0, int $offset = 0): array
    {
        return [1];
    }

    public function exists(int $siteId): bool
    {
        return $siteId === 1;
    }
}
