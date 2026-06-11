<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

/**
 * A snapshot of which add-ons are active and which feature flags are on, gathered from
 * WordPress by the screen and handed to the pure manager so the manager never reads WP.
 */
final class AddonState
{
    /**
     * @param list<string> $activeSlugs  active add-on slugs (from active_plugins)
     * @param list<string> $enabledFlags feature-flag slugs currently on
     */
    public function __construct(
        public readonly array $activeSlugs = [],
        public readonly array $enabledFlags = [],
    ) {
    }

    public function isActive(string $slug): bool
    {
        return in_array($slug, $this->activeSlugs, true);
    }

    public function flagOn(string $flag): bool
    {
        return in_array($flag, $this->enabledFlags, true);
    }
}
