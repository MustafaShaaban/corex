<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * Archived sites remain enumerable because they can be un-archived and must not
 * return with stale schema; deleted and spam sites cannot rejoin that lifecycle.
 */
final class WpNetworkContext implements NetworkContext
{
    public function id(): int
    {
        return get_current_network_id();
    }

    public function mainSiteId(): int
    {
        return (int) get_network()->site_id;
    }

    public function siteCount(): int
    {
        return (int) get_sites([
            'count' => true,
            'deleted' => 0,
            'spam' => 0,
        ]);
    }

    public function siteIds(int $limit = 0, int $offset = 0): array
    {
        return array_map('intval', get_sites([
            'fields' => 'ids',
            'number' => $limit ?: 0,
            'offset' => $offset,
            'orderby' => 'id',
            'order' => 'ASC',
            'deleted' => 0,
            'spam' => 0,
        ]));
    }

    public function exists(int $siteId): bool
    {
        return get_site($siteId) !== null;
    }
}
