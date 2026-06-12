<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;

/**
 * A DataSource over a Corex-managed custom table (spec 038): it exposes the table's declared
 * columns and shapes each row to `id` + exactly those columns (extra columns dropped, missing
 * ones defaulted to ''), so a managed table appears in Corex → Data like any other source with
 * no new UI. The `$wpdb` access lives in the injected reader, so this shaping is unit-tested.
 */
final class TableDataSource implements DataSource
{
    public function __construct(
        private readonly ManagedTable $table,
        private readonly TableDataReader $reader,
    ) {
    }

    public function key(): string
    {
        return 'table-' . $this->table->name;
    }

    public function label(): string
    {
        return $this->table->label;
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    public function columns(): array
    {
        return $this->table->columns;
    }

    /**
     * @return list<array<string,scalar>>
     */
    public function rows(int $page, int $perPage): array
    {
        $columns = $this->table->columnIds();

        return array_map(
            function (array $record) use ($columns): array {
                $row = ['id' => $record['id'] ?? 0];

                foreach ($columns as $column) {
                    $row[$column] = $record[$column] ?? '';
                }

                return $row;
            },
            $this->reader->page($this->table->name, $columns, max(1, $page), max(1, $perPage)),
        );
    }

    public function total(): int
    {
        return $this->reader->total($this->table->name);
    }

    public function delete(int $id): bool
    {
        return $this->reader->delete($this->table->name, $id);
    }
}
