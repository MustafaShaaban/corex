<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Assets;

use RuntimeException;

defined('ABSPATH') || exit;

/**
 * Maps a base name (e.g. `corex`, a theme/plugin, or a generated client) to its {@see AssetManager}
 * (spec 062). The asset facades (Style/Script/Image/Picture) resolve a manager through this registry,
 * so one site can enqueue/render framework, theme, plugin, and client assets with the correct base
 * URL/version each. The first base registered is the default; a later registration can claim it.
 */
final class AssetRegistry
{
    /** @var array<string,AssetManager> */
    private array $managers = [];

    private string $default = '';

    public function register(string $name, AssetManager $manager, bool $asDefault = false): void
    {
        $this->managers[$name] = $manager;

        if ($asDefault || $this->default === '') {
            $this->default = $name;
        }
    }

    public function has(string $name): bool
    {
        return isset($this->managers[$name]);
    }

    /** The manager for $name, or the default when $name is null. */
    public function manager(?string $name = null): AssetManager
    {
        $name ??= $this->default;

        if (! isset($this->managers[$name])) {
            throw new RuntimeException(sprintf('No asset base registered for "%s".', $name));
        }

        return $this->managers[$name];
    }

    public function defaultName(): string
    {
        return $this->default;
    }

    public function setDefault(string $name): void
    {
        if (! isset($this->managers[$name])) {
            throw new RuntimeException(sprintf('Cannot default to unregistered asset base "%s".', $name));
        }

        $this->default = $name;
    }
}
