<?php

/**
 * @package Corex\Kit
 */

declare(strict_types=1);

namespace Corex\Kit;

defined('ABSPATH') || exit;

/**
 * Holds the registered blueprints by name. Pure. Enables tooling / a future setup
 * wizard to discover and select a kit.
 */
final class BlueprintRegistry
{
    /**
     * @var array<string,Blueprint>
     */
    private array $blueprints = [];

    public function register(Blueprint $blueprint): void
    {
        $this->blueprints[$blueprint->name()] = $blueprint;
    }

    public function find(string $name): ?Blueprint
    {
        return $this->blueprints[$name] ?? null;
    }

    /**
     * @return list<Blueprint>
     */
    public function all(): array
    {
        return array_values($this->blueprints);
    }
}
