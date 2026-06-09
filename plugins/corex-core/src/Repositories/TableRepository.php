<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Repositories;

defined('ABSPATH') || exit;

use Corex\Database\Casts\Caster;
use Corex\Database\Schema\Migrator;
use InvalidArgumentException;

/**
 * Typed CRUD on a custom table — the only layer that runs the queries (Principle
 * III). Values cast to/from their declared types; every query containing a variable
 * is parameterized via `$wpdb->prepare` (table/column identifiers are code-defined,
 * never request input). A subclass declares its table() and casts().
 */
abstract class TableRepository
{
    public function __construct(
        protected readonly Caster $caster,
        protected readonly Migrator $migrator,
    ) {
    }

    /**
     * Logical table name (without prefix/namespace).
     */
    abstract protected function table(): string;

    /**
     * @return array<string,string> column => cast type
     */
    abstract protected function casts(): array;

    /**
     * @param array<string,mixed> $attributes
     */
    public function insert(array $attributes): int
    {
        global $wpdb;

        $wpdb->insert($this->name(), $this->serialize($attributes));

        return (int) $wpdb->insert_id;
    }

    /**
     * @return array<string,mixed>|null
     */
    public function find(int $id): ?array
    {
        global $wpdb;

        $row = $wpdb->get_row(
            $wpdb->prepare('SELECT * FROM ' . $this->name() . ' WHERE id = %d', $id),
            ARRAY_A
        );

        return is_array($row) ? $this->hydrate($row) : null;
    }

    /**
     * @param array<string,mixed> $attributes
     */
    public function update(int $id, array $attributes): void
    {
        global $wpdb;

        $wpdb->update($this->name(), $this->serialize($attributes), ['id' => $id]);
    }

    public function delete(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete($this->name(), ['id' => $id]);
    }

    /**
     * @return list<array<string,mixed>>
     */
    public function where(string $column, mixed $value): array
    {
        global $wpdb;

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT * FROM ' . $this->name() . ' WHERE ' . $this->identifier($column) . ' = %s', $value),
            ARRAY_A
        );

        return array_map([$this, 'hydrate'], is_array($rows) ? $rows : []);
    }

    private function name(): string
    {
        return $this->migrator->fullName($this->table());
    }

    /**
     * @param array<string,mixed> $attributes
     *
     * @return array<string,mixed>
     */
    private function serialize(array $attributes): array
    {
        $casts  = $this->casts();
        $stored = [];

        foreach ($attributes as $key => $value) {
            $stored[$key] = isset($casts[$key]) ? $this->caster->toStore($value, $casts[$key]) : $value;
        }

        return $stored;
    }

    /**
     * @param array<string,mixed> $row
     *
     * @return array<string,mixed>
     */
    private function hydrate(array $row): array
    {
        foreach ($this->casts() as $key => $type) {
            if (array_key_exists($key, $row)) {
                $row[$key] = $this->caster->toPhp($row[$key], $type);
            }
        }

        return $row;
    }

    private function identifier(string $column): string
    {
        if (preg_match('/^[a-z0-9_]+$/i', $column) !== 1) {
            throw new InvalidArgumentException(sprintf('Unsafe column name: "%s".', $column));
        }

        return $column;
    }
}
