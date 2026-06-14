<?php

/**
 * Unit tests for the CSV export assembly (spec 045: US2, FR-005/FR-006/FR-007).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Config\Data\CsvWriter;
use Corex\Config\Data\DataExportController;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\QueryableDataSource;

/** A queryable source that returns one page of rows then nothing (so collect() terminates). */
function exportSource(string $key, array $page1): QueryableDataSource
{
    return new class($key, $page1) implements QueryableDataSource {
        public function __construct(private string $k, private array $page1)
        {
        }

        public function key(): string
        {
            return $this->k;
        }

        public function label(): string
        {
            return 'X';
        }

        public function columns(): array
        {
            return [['id' => 'date', 'label' => 'Date'], ['id' => 'form', 'label' => 'Form']];
        }

        public function rows(int $page, int $perPage): array
        {
            return [];
        }

        public function total(): int
        {
            return count($this->page1);
        }

        public function query(DataQuery $query): array
        {
            return $query->page === 1 ? $this->page1 : [];
        }

        public function count(DataQuery $query): int
        {
            return count($this->page1);
        }

        public function record(int $id): ?array
        {
            return null;
        }

        public function delete(int $id): bool
        {
            return false;
        }
    };
}

it('returns null for an unknown source', function () {
    $controller = new DataExportController(new DataRegistry(), new CsvWriter());

    expect($controller->csvFor('nope', DataQuery::from([])))->toBeNull();
});

it('writes a header + the filtered rows, only the declared columns (no secret)', function () {
    $registry = new DataRegistry();
    $registry->register(exportSource('things', [
        ['date' => '2026-06-13', 'form' => 'contact', 'secret' => 'LEAK'],
        ['date' => '2026-06-12', 'form' => 'signup', 'secret' => 'LEAK'],
    ]));

    $csv = (new DataExportController($registry, new CsvWriter()))->csvFor('things', DataQuery::from([]));

    expect($csv)->toBe("Date,Form\r\n2026-06-13,contact\r\n2026-06-12,signup\r\n")
        ->and($csv)->not->toContain('LEAK');
});

it('produces a header-only CSV when there are no records', function () {
    $registry = new DataRegistry();
    $registry->register(exportSource('things', []));

    $csv = (new DataExportController($registry, new CsvWriter()))->csvFor('things', DataQuery::from([]));

    expect($csv)->toBe("Date,Form\r\n");
});
