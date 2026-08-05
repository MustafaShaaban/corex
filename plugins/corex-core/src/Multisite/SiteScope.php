<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

interface SiteScope
{
    public function currentSiteId(): int;

    /**
     * Register a memoizing service for invalidation. Idempotent per instance.
     */
    public function register(SiteScoped $service): void;

    /**
     * Run the callback with the site current, restoring the previous site even if it throws.
     *
     * @template T
     * @param \Closure():T $callback
     * @return T
     */
    public function run(int $siteId, \Closure $callback): mixed;
}
