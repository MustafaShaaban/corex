<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Addons;

defined('ABSPATH') || exit;

use Corex\Multisite\ActivationScope;

/**
 * A snapshot of which add-ons are active and which feature flags are on, gathered from
 * WordPress by the screen and handed to the pure manager so the manager never reads WP.
 */
final class AddonState
{
    /**
     * @param list<string>                   $activeSlugs  active add-on slugs from any activation scope
     * @param list<string>                   $enabledFlags feature-flag slugs currently on
     * @param array<string, ActivationScope> $scopes       activation scopes keyed by add-on slug
     */
    public function __construct(
        public readonly array $activeSlugs = [],
        public readonly array $enabledFlags = [],
        public readonly array $scopes = [],
    ) {
    }

    public function isActive(string $slug): bool
    {
        return $this->scopeOf($slug)->isActive();
    }

    public function scopeOf(string $slug): ActivationScope
    {
        $scope = $this->scopes[$slug] ?? null;

        if ($scope instanceof ActivationScope) {
            return $scope;
        }

        return in_array($slug, $this->activeSlugs, true)
            ? ActivationScope::Site
            : ActivationScope::None;
    }

    public function flagOn(string $flag): bool
    {
        return in_array($flag, $this->enabledFlags, true);
    }
}
