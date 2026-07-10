<?php

/**
 * Column-scoped export job/history tests (spec 068 T121 / FR-062, FR-068).
 *
 * @package Corex\Tests\Unit\DataModels
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataAccessPolicy;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataQueryService;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\QueryableDataSource;
use Corex\Config\DataModels\DataExportArtifactWriter;
use Corex\Config\DataModels\DataExportArtifact;
use Corex\Config\DataModels\DataExportJobHandler;
use Corex\Config\DataModels\DataExportJobQueue;
use Corex\Config\DataModels\DataExportRequest;
use Corex\Config\DataModels\DataExportRun;
use Corex\Config\DataModels\DataExportService;
use Corex\Config\DataModels\DataExportStore;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Jobs\BoundedJob;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('provides durable filtered selected and all export contracts', function () {
    expect(class_exists(Corex\Config\DataModels\DataExportService::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\DataExportRequest::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\DataExportRun::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\DataExportStore::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\DataExportJobQueue::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\DataExportJobHandler::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\DataExportArtifact::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\DataExportArtifactWriter::class))->toBeTrue();
});

/** @return array{DataExportService,DataExportStore,DataExportJobQueue,DataSourceService,ActivityRepository} */
function exportService(bool $xlsx = true): array
{
    $source = new class($xlsx) implements DataSource, QueryableDataSource, CapabilityAwareDataSource, FieldAwareDataSource {
        private array $records = [
            ['id' => 1, 'name' => '=Ada', 'email' => 'ada@example.com', 'status' => 'active', 'secret' => 'drop'],
            ['id' => 2, 'name' => 'Grace', 'email' => 'grace@example.com', 'status' => 'inactive', 'secret' => 'drop'],
            ['id' => 3, 'name' => 'Linus', 'email' => 'linus@example.com', 'status' => 'active', 'secret' => 'drop'],
        ];
        public function __construct(private bool $xlsx) {}
        public function key(): string { return 'contacts'; }
        public function label(): string { return 'Contacts'; }
        public function columns(): array { return [['id' => 'name', 'label' => 'Name'], ['id' => 'email', 'label' => 'Email'], ['id' => 'status', 'label' => 'Status']]; }
        public function rows(int $page, int $perPage): array { return array_slice($this->records, ($page - 1) * $perPage, $perPage); }
        public function total(): int { return count($this->records); }
        public function delete(int $id): bool { return false; }
        public function query(DataQuery $query): array
        {
            $rows = $this->matching($query);

            return array_slice($rows, ($query->page - 1) * $query->perPage, $query->perPage);
        }
        public function count(DataQuery $query): int { return count($this->matching($query)); }
        public function record(int $id): ?array
        {
            foreach ($this->records as $record) if ($record['id'] === $id) return $record;

            return null;
        }
        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'contacts', read: true, query: true, schema: true, detail: true,
                create: false, update: false, delete: false, bulkUpdate: false, bulkDelete: false,
                importDryRun: false, importCommit: false, exportCsv: true, exportXlsx: $this->xlsx,
                migrations: false, rollback: false, maxPageSize: 100,
                permissionMap: ['query' => 'corex_manage_data', 'detail' => 'corex_manage_data', 'export_csv' => 'corex_manage_data', 'export_xlsx' => 'corex_manage_data'],
            );
        }
        public function fields(): array
        {
            return [
                new DataField('name', 'Name', DataField::TYPE_TEXT, true, false, true, ['contains'], true, DataField::PERSONAL_IDENTITY, [], []),
                new DataField('email', 'Email', DataField::TYPE_EMAIL, true, false, true, ['equals'], true, DataField::PERSONAL_CONTACT, [], []),
                new DataField('status', 'Status', DataField::TYPE_SELECT, false, true, true, ['equals'], true, DataField::PERSONAL_NONE, ['options' => ['active', 'inactive']], []),
            ];
        }
        private function matching(DataQuery $query): array
        {
            return array_values(array_filter($this->records, static fn (array $record): bool =>
                ($query->filters['status'] ?? '') === '' || $record['status'] === $query->filters['status']));
        }
    };
    $registry = new DataRegistry();
    $registry->register($source);
    $policy = new class implements DataAccessPolicy {
        public function allows(int $actorId, string $ability): bool { return $actorId === 7; }
    };
    $sources = new DataSourceService($registry, $policy);
    $queries = new DataQueryService($registry, $sources);
    $store = new class implements DataExportStore {
        /** @var array<int,DataExportRun> */ public array $runs = [];
        /** @var array<int,string> */ public array $artifacts = [];
        public function create(DataExportRun $run): DataExportRun { $run = $run->withId(count($this->runs) + 1); return $this->runs[$run->id] = $run; }
        public function attachJob(int $id, int $jobId): DataExportRun { return $this->runs[$id] = $this->runs[$id]->withJob($jobId); }
        public function find(int $id): ?DataExportRun { return $this->runs[$id] ?? null; }
        public function findByHash(string $hash): ?DataExportRun { foreach ($this->runs as $run) if ($run->inputHash === $hash) return $run; return null; }
        public function history(int $actorId, bool $manageAll, int $limit): array { return array_slice(array_values(array_filter($this->runs, static fn (DataExportRun $run): bool => $manageAll || $run->actorId === $actorId)), 0, $limit); }
        public function saveArtifact(int $id, string $artifact): void { $this->artifacts[$id] = $artifact; }
        public function artifact(int $id): ?string { return $this->artifacts[$id] ?? null; }
        public function finish(int $id, int $rows): void { $this->runs[$id] = $this->runs[$id]->completed($rows); }
    };
    $queue = new class implements DataExportJobQueue {
        /** @var list<DataExportRun> */ public array $runs = [];
        public function enqueue(DataExportRun $run): int { $this->runs[] = $run; return 52; }
    };
    $activity = new class implements ActivityRepository {
        /** @var list<ActivityEvent> */ public array $events = [];
        public function append(ActivityEvent $event): ActivityEvent { $event = $event->withId(count($this->events) + 1); $this->events[] = $event; return $event; }
        public function find(int $id): ?ActivityEvent { return $this->events[$id - 1] ?? null; }
        public function query(array $filters = [], int $page = 1, int $perPage = 20): array { return $this->events; }
        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int { return 0; }
    };

    return [new DataExportService($sources, $queries, $store, $queue), $store, $queue, $sources, $activity];
}

function exportRequest(array $changes = []): DataExportRequest
{
    return DataExportRequest::from($changes + [
        'actor_id' => 7, 'source_key' => 'contacts', 'scope' => 'filtered',
        'query' => ['filters' => ['status' => 'active']], 'selected_ids' => [],
        'columns' => ['name', 'status'], 'format' => 'csv', 'personal_data_acknowledged' => true,
    ]);
}

it('queues a column-scoped filtered export with truthful count and personal-data classes', function () {
    [$service, , $queue] = exportService();

    $run = $service->request(exportRequest());

    expect($run->recordCount)->toBe(2)
        ->and($run->columns)->toBe(['name', 'status'])
        ->and($run->personalDataClasses)->toBe(['identity'])
        ->and($run->jobId)->toBe(52)
        ->and($queue->runs)->toHaveCount(1);
});

it('requires personal-data acknowledgement and rejects undeclared columns or formats', function () {
    [$service] = exportService(false);

    expect(fn () => $service->request(exportRequest(['personal_data_acknowledged' => false])))
        ->toThrow(DomainException::class, 'acknowledge')
        ->and(fn () => $service->request(exportRequest(['columns' => ['secret']])))
        ->toThrow(InvalidArgumentException::class, 'column')
        ->and(fn () => $service->request(exportRequest(['format' => 'xlsx'])))
        ->toThrow(DomainException::class, 'support');
});

it('validates exact selected records and scopes history and downloads to the actor', function () {
    [$service] = exportService();
    $run = $service->request(exportRequest([
        'scope' => 'selected', 'selected_ids' => [3, 1, 3], 'query' => [], 'columns' => ['status'],
    ]));

    expect($run->selectedIds)->toBe([1, 3])
        ->and($run->recordCount)->toBe(2)
        ->and($service->history(7))->toHaveCount(1)
        ->and(fn () => $service->request(exportRequest(['scope' => 'selected', 'selected_ids' => [99], 'query' => []])))
        ->toThrow(DomainException::class, 'unavailable')
        ->and(fn () => $service->download(8, $run->id, false))
        ->toThrow(DomainException::class, 'unavailable');
});

it('streams only selected columns into a formula-safe artifact and completes history', function () {
    [$service, $store, , $sources, $activity] = exportService();
    $run = $service->request(exportRequest());
    $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $job = BoundedJob::queued(DataExportJobHandler::KIND, 7, 2, $run->inputHash, $now)->withId(52)->start($now);
    $handler = new DataExportJobHandler($sources, $store, new DataExportArtifactWriter(), new ActivityService($activity));

    $result = $handler->handle($job, 25);
    $download = $service->download(7, $run->id, false);

    expect($result->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($download['filename'])->toBe('corex-contacts-1.csv')
        ->and($download['mime'])->toBe('text/csv')
        ->and($download['content'])->toContain("Name,Status\r\n'=Ada,active\r\nLinus,active\r\n")
        ->and($download['content'])->not->toContain('example.com')->not->toContain('drop')
        ->and(fn () => $service->download(7, $run->id, false, 'other-source'))
        ->toThrow(DomainException::class, 'unavailable')
        ->and($store->find($run->id)?->state)->toBe(DataExportRun::STATE_COMPLETED)
        ->and($activity->events[0]->kind)->toBe('data.export.completed');
});

it('builds a valid inline-string XLSX artifact without formula cells', function () {
    $artifact = (new DataExportArtifactWriter())->append(
        DataExportArtifact::start('xlsx'),
        [['key' => 'name', 'label' => 'Name']],
        [['name' => '=1+2'], ['name' => 'Ada & Grace']],
        true,
    )->content;
    $path = tempnam(sys_get_temp_dir(), 'corex-xlsx-test-');
    file_put_contents($path, $artifact);
    $zip = new ZipArchive();
    $opened = $zip->open($path);
    $sheet = $opened === true ? (string) $zip->getFromName('xl/worksheets/sheet1.xml') : '';
    if ($opened === true) $zip->close();
    unlink($path);

    expect(substr($artifact, 0, 2))->toBe('PK')
        ->and($sheet)->toContain('=1+2')->toContain('Ada &amp; Grace')
        ->and($sheet)->not->toContain('<f>');
});
