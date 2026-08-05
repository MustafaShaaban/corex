<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

interface SiteScoped
{
    /**
     * Discard anything derived from the site that was current when it was computed.
     * MUST be idempotent and MUST NOT read from WordPress — recomputation is lazy,
     * on the next accessor call.
     */
    public function forgetSiteState(int $siteId): void;
}
