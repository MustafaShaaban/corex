<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * Constant answers keep a single-site request free of multisite hooks and queries (spec FR-002, FR-004).
 */
final class SingleSiteMultisiteContext implements MultisiteContext
{
    public function enabled(): bool
    {
        return false;
    }

    public function subdomainInstall(): bool
    {
        return false;
    }

    public function isNetworkAdmin(): bool
    {
        return false;
    }

    public function isSwitched(): bool
    {
        return false;
    }
}
