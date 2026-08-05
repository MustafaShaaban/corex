<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

final class WpMultisiteContext implements MultisiteContext
{
    public function enabled(): bool
    {
        return is_multisite();
    }

    public function subdomainInstall(): bool
    {
        return defined('SUBDOMAIN_INSTALL') && SUBDOMAIN_INSTALL;
    }

    public function isNetworkAdmin(): bool
    {
        return is_network_admin();
    }

    public function isSwitched(): bool
    {
        return ms_is_switched();
    }
}
