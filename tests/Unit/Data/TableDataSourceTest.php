<?php

/**
 * Unit tests for the table-backed DataSource shaping (spec 038: FR-002). The $wpdb access is a
 * fake reader, so the column/row/delete shaping is checked headlessly.
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Config\Data\TableDataReader;
use Corex\Config\Data\TableDataSource;
use Corex\Database\Schema\ManagedTable;

function tableReader(array $rows, int $total): TableDataReader
{
    return new class($rows, $total) implements TableDataReader {
        public array $deleted = [];

        public function __construct(private array $rows, private int $total)
        {
        }

        public function page(string $table, array $columns, int $page, int $perPage): array
        {
            return $this->rows;
        }

        public function total(string $table): int
        {
            return $this->total;
        }

        public function delete(string $table, int $id): bool
        {
            $this->deleted[] = $id;

            return $id > 0;
        }

        public function query(string $table, array $columns, \Corex\Config\Data\DataQuery $query): array
        {
            return array_slice($this->rows, ($query->page - 1) * $query->perPage, $query->perPage);
        }

        public function countQuery(string $table, array $columns, \Corex\Config\Data\DataQuery $query): int
        {
            return $this->total;
        }

        public function find(string $table, array $columns, int $id): ?array
        {
            foreach ($this->rows as $row) {
                if ((int) ($row['id'] ?? 0) === $id) return $row;
            }
            return null;
        }
    };
}

function source(TableDataReader $reader): TableDataSource
{
    $table = new ManagedTable('invoices', 'Invoices', [
        ['id' => 'number', 'label' => 'Number'],
        ['id' => 'total', 'label' => 'Total'],
    ]);

    return new TableDataSource($table, $reader);
}

it('keys the source by table name and exposes the managed columns', function () {
    $s = source(tableReader([], 0));

    expect($s->key())->toBe('table-invoices')
        ->and($s->label())->toBe('Invoices')
        ->and($s->columns())->toBe([
            ['id' => 'number', 'label' => 'Number'],
            ['id' => 'total', 'label' => 'Total'],
        ]);
});

it('shapes rows to id + the declared columns, ignoring extra columns and defaulting missing ones', function () {
    $reader = tableReader([
        ['id' => 5, 'number' => 'INV-1', 'total' => '100', 'secret' => 'x'],
        ['id' => 6, 'number' => 'INV-2'],
    ], 2);

    $rows = source($reader)->rows(1, 20);

    expect($rows[0])->toBe(['id' => 5, 'number' => 'INV-1', 'total' => '100'])
        ->and($rows[1])->toBe(['id' => 6, 'number' => 'INV-2', 'total' => ''])
        ->and($rows[0])->not->toHaveKey('secret');
});

it('delegates total and delete to the reader with the table name', function () {
    $reader = tableReader([], 42);
    $s = source($reader);

    expect($s->total())->toBe(42)
        ->and($s->delete(7))->toBeTrue()
        ->and($reader->deleted)->toBe([7]);
});

it('queries pages and exposes truthful schema and detail through the reader', function () {
    $reader = tableReader([
        ['id' => 5, 'number' => 'INV-1', 'total' => '100', 'secret' => 'drop'],
        ['id' => 6, 'number' => 'INV-2', 'total' => '200'],
    ], 2);
    $source = source($reader);
    $query = \Corex\Config\Data\DataQuery::from(['page' => 1, 'per_page' => 1]);

    expect($source->query($query))->toBe([['id' => 5, 'number' => 'INV-1', 'total' => '100']])
        ->and($source->count($query))->toBe(2)
        ->and($source->record(6))->toBe(['id' => 6, 'number' => 'INV-2', 'total' => '200'])
        ->and($source->schema())->toBe([
            ['name' => 'Number', 'type' => 'text'],
            ['name' => 'Total', 'type' => 'text'],
        ]);
});

it('normalizes managed table underscores into URL-safe source keys', function () {
    $source = new TableDataSource(
        new ManagedTable('invoice_items', 'Invoice items', [['id' => 'name', 'label' => 'Name']]),
        tableReader([], 0),
    );

    expect($source->key())->toBe('table-invoice-items')
        ->and($source->capabilities()->sourceKey)->toBe('table-invoice-items');
});
