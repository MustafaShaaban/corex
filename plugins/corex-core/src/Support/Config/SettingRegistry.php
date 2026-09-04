<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support\Config;

defined('ABSPATH') || exit;

/**
 * Collects module-owned setting metadata before configuration is read (spec 100 FR-023–FR-024).
 *
 * The host filter is applied lazily because providers populate this registry throughout their
 * register pass. Filter entries whose values are not SettingScope instances are ignored, keeping
 * malformed host configuration from breaking resolution.
 */
final class SettingRegistry
{
    /**
     * @var array<string, SettingDefinition>
     */
    private array $definitions = [];

    /**
     * @var array<string, SettingScope>|null
     */
    private ?array $filteredScopes = null;

    public function add(SettingDefinition $definition): void
    {
        $this->definitions[$definition->key] = $definition;
        $this->filteredScopes = null;
    }

    public function get(string $key): ?SettingDefinition
    {
        return $this->definitions[$key] ?? null;
    }

    public function scopeOf(string $key): SettingScope
    {
        return $this->scopes()[$key] ?? SettingScope::Site;
    }

    /**
     * @return list<SettingDefinition>
     */
    public function all(): array
    {
        return array_values(array_map(
            fn (SettingDefinition $definition): SettingDefinition => $this->withEffectiveScope($definition),
            $this->definitions,
        ));
    }

    /**
     * @return list<SettingDefinition>
     */
    public function withScope(SettingScope $scope): array
    {
        return array_values(array_filter(
            $this->all(),
            static fn (SettingDefinition $definition): bool => $definition->scope === $scope,
        ));
    }

    /**
     * @return array<string, SettingScope>
     */
    private function scopes(): array
    {
        if ($this->filteredScopes !== null) {
            return $this->filteredScopes;
        }

        $scopes = [];

        foreach ($this->definitions as $key => $definition) {
            $scopes[$key] = $definition->scope;
        }

        if (! function_exists('apply_filters')) {
            return $this->filteredScopes = $scopes;
        }

        /** @var mixed $filtered */
        $filtered = apply_filters('corex_setting_scopes', $scopes);

        if (! is_array($filtered)) {
            return $this->filteredScopes = $scopes;
        }

        foreach ($filtered as $key => $scope) {
            if (is_string($key) && $scope instanceof SettingScope) {
                $scopes[$key] = $scope;
            }
        }

        return $this->filteredScopes = $scopes;
    }

    private function withEffectiveScope(SettingDefinition $definition): SettingDefinition
    {
        $scope = $this->scopeOf($definition->key);

        if ($scope === $definition->scope) {
            return $definition;
        }

        return new SettingDefinition(
            $definition->key,
            $scope,
            $definition->label,
            $definition->description,
        );
    }
}
