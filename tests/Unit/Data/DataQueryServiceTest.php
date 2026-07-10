<?php

/**
 * Real source query/filter/sort/page service tests (spec 068 T113 / FR-059-FR-061).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataAccessPolicy;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataQueryService;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\QueryableDataSource;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;

function queryServiceSource(): QueryableDataSource
{
    return new class() implements QueryableDataSource, CapabilityAwareDataSource, FieldAwareDataSource {
        public ?DataQuery $received = null;
        private array $records = [
            ['id' => 1, 'name' => 'Alpha', 'status' => 'open'],
            ['id' => 2, 'name' => 'Beta', 'status' => 'closed'],
            ['id' => 3, 'name' => 'Gamma', 'status' => 'open'],
        ];

        public function key(): string { return 'tickets'; }
        public function label(): string { return 'Tickets'; }
        public function columns(): array { return [['id' => 'name', 'label' => 'Name'], ['id' => 'status', 'label' => 'Status']]; }
        public function rows(int $page, int $perPage): array { return array_slice($this->records, ($page - 1) * $perPage, $perPage); }
        public function total(): int { return count($this->records); }
        public function delete(int $id): bool { return false; }

        public function query(DataQuery $query): array
        {
            $this->received = $query;
            $records = array_values(array_filter($this->records, static function (array $record) use ($query): bool {
                $search = $query->search === '' || str_contains(strtolower($record['name']), strtolower($query->search));
                $status = ($query->filters['status'] ?? '') === '' || $record['status'] === $query->filters['status'];

                return $search && $status;
            }));
            if ($query->sortColumn === 'name') {
                usort($records, static fn (array $left, array $right): int =>
                    ($query->sortDir === 'asc' ? 1 : -1) * ($left['name'] <=> $right['name']));
            }

            return array_slice($records, ($query->page - 1) * $query->perPage, $query->perPage);
        }

        public function count(DataQuery $query): int
        {
            return count(array_filter($this->records, static fn (array $record): bool =>
                (($query->filters['status'] ?? '') === '' || $record['status'] === $query->filters['status'])
                && ($query->search === '' || str_contains(strtolower($record['name']), strtolower($query->search)))));
        }

        public function record(int $id): ?array
        {
            foreach ($this->records as $record) {
                if ($record['id'] === $id) return $record;
            }
            return null;
        }

        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'tickets', read: true, query: true, schema: true, detail: true,
                create: false, update: false, delete: false, bulkUpdate: false, bulkDelete: false,
                importDryRun: false, importCommit: false, exportCsv: true, exportXlsx: false,
                migrations: false, rollback: false, maxPageSize: 2,
                permissionMap: ['read' => 'corex_manage_data', 'query' => 'corex_manage_data', 'detail' => 'corex_manage_data'],
            );
        }

        public function fields(): array
        {
            return [
                new DataField('name', 'Name', DataField::TYPE_TEXT, false, true, true, ['contains'], true, DataField::PERSONAL_NONE, [], []),
                new DataField('status', 'Status', DataField::TYPE_SELECT, false, true, true, ['equals'], false, DataField::PERSONAL_NONE, [], []),
            ];
        }
    };
}

function dataQueryService(bool $allowed = true): array
{
    $source = queryServiceSource();
    $registry = new DataRegistry();
    $registry->register($source);
    $policy = new class($allowed) implements DataAccessPolicy {
        public function __construct(private bool $allowed) {}
        public function allows(int $actorId, string $ability): bool { return $this->allowed && $actorId === 7; }
    };
    $sources = new DataSourceService($registry, $policy);

    return [new DataQueryService($registry, $sources), $source];
}

it('queries the real filtered sorted result and clamps to the source page size', function () {
    [$service, $source] = dataQueryService();
    $result = $service->query(7, 'tickets', DataQuery::from([
        'search' => 'a', 'filters' => ['status' => 'open'], 'sort' => 'name', 'dir' => 'desc', 'per_page' => 50,
    ]));

    expect($result['rows'])->toHaveCount(2)
        ->and(array_column($result['rows'], 'name'))->toBe(['Gamma', 'Alpha'])
        ->and($result)->toMatchArray(['total' => 2, 'page' => 1, 'per_page' => 2])
        ->and($source->received?->perPage)->toBe(2);
});

it('rejects undeclared filters and non-sortable fields instead of widening queries', function () {
    [$service] = dataQueryService();

    expect(fn () => $service->query(7, 'tickets', DataQuery::from(['filters' => ['secret' => 'x']])))
        ->toThrow(InvalidArgumentException::class, 'filter')
        ->and(fn () => $service->query(7, 'tickets', DataQuery::from(['sort' => 'status'])))
        ->toThrow(InvalidArgumentException::class, 'sort');
});

it('denies query and detail before invoking a source when permission is missing', function () {
    [$service, $source] = dataQueryService(false);

    expect(fn () => $service->query(7, 'tickets', DataQuery::from([])))
        ->toThrow(DomainException::class, 'permission')
        ->and(fn () => $service->detail(7, 'tickets', 1))
        ->toThrow(DomainException::class, 'permission')
        ->and($source->received)->toBeNull();
});

it('returns a source-defined detail record and hides unknown sources', function () {
    [$service] = dataQueryService();

    expect($service->detail(7, 'tickets', 2))->toMatchArray(['id' => 2, 'name' => 'Beta'])
        ->and(fn () => $service->detail(7, 'missing', 2))
        ->toThrow(DomainException::class, 'source');
});
