<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * Collects independently declared schema components without making core know
 * which product module owns a table (spec 100 FR-028, Principle IV).
 */
final class SchemaRegistry
{
    /** @var array<string, SchemaComponent> */
    private array $components = [];

    public function register(SchemaComponent $component): void
    {
        $this->components[$component->id] = $component;
    }

    /** @return list<SchemaComponent> */
    public function all(): array
    {
        return array_values($this->components);
    }

    public function get(string $id): ?SchemaComponent
    {
        return $this->components[$id] ?? null;
    }

    /** @return list<string> */
    public function tableNames(): array
    {
        $names = [];

        foreach ($this->components as $component) {
            foreach ($component->tables as $table) {
                $names[] = $table->name;
            }
        }

        return $names;
    }
}
