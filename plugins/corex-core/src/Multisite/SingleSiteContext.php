<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

/**
 * WordPress sets $blog_id to 1 in wp-settings.php on every non-multisite install,
 * so the identity answers are exact constants rather than approximations (spec FR-002).
 * url() is the sole exception because the configured home URL must be asked of WordPress.
 */
final class SingleSiteContext implements SiteContext
{
    public function id(): int
    {
        return 1;
    }

    public function networkId(): int
    {
        return 1;
    }

    public function isMainSite(): bool
    {
        return true;
    }

    public function url(): string
    {
        return home_url();
    }
}
