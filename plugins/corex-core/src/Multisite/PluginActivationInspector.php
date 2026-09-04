<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Multisite;

defined('ABSPATH') || exit;

interface PluginActivationInspector
{
    public function scopeOf(string $pluginFile): ActivationScope;

    public function isActive(string $pluginFile): bool;

    /**
     * @return list<string> Every active plugin file, any scope, deduped, network first.
     */
    public function activePluginFiles(): array;

    /**
     * @return array<string, ActivationScope>
     */
    public function scopes(): array;
}
