<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

use Corex\Multisite\ActivationScope;

/**
 * Runtime snapshot used before optional add-on service providers are loaded.
 */
final class AddonRuntimeState
{
    /**
     * @param list<string>        $activeSlugs
     * @param list<string>        $installedPluginFiles
     * @param list<string>        $enabledFlags
     * @param array<string, bool>            $externalGates
     * @param array<string, ActivationScope> $activationScopes
     */
    public function __construct(
        private readonly array $activeSlugs = [],
        private readonly array $installedPluginFiles = [],
        private readonly array $enabledFlags = [],
        private readonly array $externalGates = [],
        private readonly array $activationScopes = [],
        private readonly int $siteId = 1,
    ) {
    }

    public function isActive(string $slug): bool
    {
        return $this->scopeOf($slug)->isActive();
    }

    public function scopeOf(string $slug): ActivationScope
    {
        $scope = $this->activationScopes[$slug] ?? null;

        if ($scope instanceof ActivationScope) {
            return $scope;
        }

        return in_array($slug, $this->activeSlugs, true)
            ? ActivationScope::Site
            : ActivationScope::None;
    }

    public function siteId(): int
    {
        return $this->siteId;
    }

    public function isInstalled(AddonProvider $provider): bool
    {
        return in_array($provider->pluginFile, $this->installedPluginFiles, true);
    }

    public function flagEnabled(string $flag): bool
    {
        return in_array($flag, $this->enabledFlags, true);
    }

    public function externalGateOpen(string $gate): bool
    {
        return $this->externalGates[$gate] ?? false;
    }
}
