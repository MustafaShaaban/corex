<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

final class SingleSiteScope implements SiteScope
{
    public function currentSiteId(): int
    {
        return 1;
    }

    /**
     * Nothing can ever change site identity on a single-site install.
     */
    public function register(SiteScoped $service): void
    {
    }

    public function run(int $siteId, \Closure $callback): mixed
    {
        return $callback();
    }
}
