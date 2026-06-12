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
