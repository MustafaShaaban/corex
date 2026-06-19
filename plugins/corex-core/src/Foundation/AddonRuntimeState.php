<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Foundation;

defined('ABSPATH') || exit;

/**
 * Runtime snapshot used before optional add-on service providers are loaded.
 */
final class AddonRuntimeState
{
    /**
     * @param list<string>        $activeSlugs
     * @param list<string>        $installedPluginFiles
     * @param list<string>        $enabledFlags
     * @param array<string, bool> $externalGates
     */
    public function __construct(
        private readonly array $activeSlugs = [],
        private readonly array $installedPluginFiles = [],
        private readonly array $enabledFlags = [],
        private readonly array $externalGates = [],
    ) {
    }

    public function isActive(string $slug): bool
    {
        return in_array($slug, $this->activeSlugs, true);
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
