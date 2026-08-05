<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

final class WpSiteContext implements SiteContext
{
    public function id(): int
    {
        return get_current_blog_id();
    }

    public function networkId(): int
    {
        return get_current_network_id();
    }

    public function isMainSite(): bool
    {
        return is_main_site();
    }

    public function url(): string
    {
        return home_url();
    }
}
