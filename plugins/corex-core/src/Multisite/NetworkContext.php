<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * Provides network identity and deterministic site enumeration (spec FR-001).
 */
interface NetworkContext
{
    public function id(): int;

    public function mainSiteId(): int;

    public function siteCount(): int;

    /**
     * A limit of zero enumerates the whole network, which is unbounded work on a large one.
     * Callers that can be reached by a page render MUST pass a limit and page with the offset;
     * the unlimited form belongs to CLI and scheduled work only (spec FR-028).
     *
     * @return list<int> ordered by site id ascending; zero means all sites
     */
    public function siteIds(int $limit = 0, int $offset = 0): array;

    public function exists(int $siteId): bool;
}
