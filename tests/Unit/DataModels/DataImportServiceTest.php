<?php

/**
 * CSV mapping, dry-run, report, and confirmed commit tests (spec 068 T118 / FR-066–FR-067).
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
use Corex\Config\Data\DataSource;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\WritableDataSource;
use Corex\Config\DataModels\DataImportJobHandler;
use Corex\Config\DataModels\DataImportJobQueue;
use Corex\Config\DataModels\DataImportRequest;
use Corex\Config\DataModels\DataImportRun;
use Corex\Config\DataModels\DataImportService;
use Corex\Config\DataModels\DataImportStore;
use Corex\Config\DataModels\ImportReportWriter;
use Corex\Config\Data\DataRegistry;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Data\DataWriteAdapter;
use Corex\Jobs\BoundedJob;
use Corex\Operations\OperationResult;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('provides durable import dry-run and bounded commit contracts', function () {
    expect(class_exists(Corex\Config\DataModels\DataImportService::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\DataImportRequest::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\DataImportRun::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\DataImportStore::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\DataImportJobQueue::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\DataImportJobHandler::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\ImportReportWriter::class))->toBeTrue();
});

/** @return array{DataImportService,DataWriteAdapter,DataImportStore,DataImportJobQueue,DataSourceService,ActivityRepository} */
function importService(bool $allowed = true): array
{
    $adapter = new class implements DataWriteAdapter {
        /** @var list<array<string,mixed>> */
        public array $created = [];
        public function create(array $values): OperationResult
        {
            $this->created[] = $values;
            $now = new DateTimeImmutable('2026-07-04T11:00:00+00:00');

            return new OperationResult(
                '123e4567-e89b-42d3-a456-426614174000', OperationResult::STATE_COMPLETED,
                'Created.', [], [count($this->created)], $now, $now,
            );
        }
        public function update(array $recordIds, array $values): OperationResult { throw new RuntimeException('Not used.'); }
        public function delete(array $recordIds): OperationResult { throw new RuntimeException('Not used.'); }
    };
    $source = new class($adapter) implements DataSource, CapabilityAwareDataSource, FieldAwareDataSource, WritableDataSource {
        public function __construct(private DataWriteAdapter $adapter) {}
        public function key(): string { return 'contacts'; }
        public function label(): string { return 'Contacts'; }
        public function columns(): array { return []; }
        public function rows(int $page, int $perPage): array { return []; }
        public function total(): int { return 0; }
        public function delete(int $id): bool { return false; }
        public function writeAdapter(): DataWriteAdapter { return $this->adapter; }
        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'contacts', read: true, query: true, schema: true, detail: true,
                create: true, update: true, delete: true, bulkUpdate: true, bulkDelete: true,
                importDryRun: true, importCommit: true, exportCsv: true, exportXlsx: false,
                migrations: false, rollback: false, maxPageSize: 100,
                permissionMap: [
                    'import_dry_run' => 'corex_manage_data_models',
                    'import_commit' => 'corex_manage_data_models',
                ],
            );
        }
        public function fields(): array
        {
            return [
                new DataField('name', 'Name', DataField::TYPE_TEXT, true, false, false, ['contains'], true, DataField::PERSONAL_IDENTITY, ['max_length' => 80], ['full_name']),
                new DataField('email', 'Email', DataField::TYPE_EMAIL, true, false, false, ['equals'], true, DataField::PERSONAL_CONTACT, [], ['email_address']),
                new DataField('status', 'Status', DataField::TYPE_SELECT, false, true, false, ['equals'], true, DataField::PERSONAL_NONE, ['options' => ['active', 'inactive']], []),
                new DataField('id', 'ID', DataField::TYPE_ID, false, false, true, ['equals'], true, DataField::PERSONAL_NONE, [], []),
            ];
        }
    };
    $registry = new DataRegistry();
    $registry->register($source);
    $policy = new class($allowed) implements DataAccessPolicy {
        public function __construct(private bool $allowed) {}
        public function allows(int $actorId, string $ability): bool { return $this->allowed && $actorId === 7; }
    };
    $sources = new DataSourceService($registry, $policy);
    $store = new class implements DataImportStore {
        /** @var array<int,DataImportRun> */
        public array $runs = [];
        public function create(DataImportRun $run): DataImportRun
        {
            $run = $run->withId(count($this->runs) + 1);
            $this->runs[$run->id] = $run;

            return $run;
        }
        public function find(int $id): ?DataImportRun { return $this->runs[$id] ?? null; }
        public function findByHash(string $inputHash): ?DataImportRun
        {
            foreach ($this->runs as $run) if ($run->inputHash === $inputHash) return $run;

            return null;
        }
        public function attachJob(int $id, int $jobId): DataImportRun
        {
            $this->runs[$id] = $this->runs[$id]->withJob($jobId);

            return $this->runs[$id];
        }
        public function finish(int $id, int $succeeded, int $failed): void
        {
            $this->runs[$id] = $this->runs[$id]->withResult($succeeded, $failed);
        }
    };
    $queue = new class implements DataImportJobQueue {
        /** @var list<DataImportRun> */
        public array $runs = [];
        public function enqueue(DataImportRun $run): int { $this->runs[] = $run; return 91; }
    };
    $activity = new class implements ActivityRepository {
        /** @var list<ActivityEvent> */
        public array $events = [];
        public function append(ActivityEvent $event): ActivityEvent
        {
            $event = $event->withId(count($this->events) + 1);
            $this->events[] = $event;

            return $event;
        }
        public function find(int $id): ?ActivityEvent { return $this->events[$id - 1] ?? null; }
        public function query(array $filters = [], int $page = 1, int $perPage = 20): array { return $this->events; }
        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int { return 0; }
    };

    return [
        new DataImportService($sources, $store, $queue, new ActivityService($activity)),
        $adapter, $store, $queue, $sources, $activity,
    ];
}

function importRequest(array $changes = []): DataImportRequest
{
    return DataImportRequest::from($changes + [
        'actor_id' => 7,
        'source_key' => 'contacts',
        'header' => ['full_name', 'email_address', 'state', 'notes'],
        'rows' => [['Ada Lovelace', 'ada@example.com', 'active', 'first']],
        'mapping' => ['state' => 'status'],
        'unknown_policy' => 'ignore',
        'file_name' => 'contacts.csv',
    ]);
}

it('maps exact fields aliases and explicit columns while detecting personal data', function () {
    [$service, $adapter] = importService();

    $run = $service->dryRun(importRequest());

    expect($run->state)->toBe(DataImportRun::STATE_VALID)
        ->and($run->acceptedRows)->toBe([[
            'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'status' => 'active',
        ]])
        ->and($run->rejectedRows)->toBe([])
        ->and($run->unknownColumns)->toBe(['notes'])
        ->and($run->personalDataClasses)->toBe(['contact', 'identity'])
        ->and($adapter->created)->toBe([]);
});

it('rejects unknown columns by policy and reports row-level validation reasons', function () {
    [$service] = importService();
    $unknown = $service->dryRun(importRequest(['unknown_policy' => 'reject']));
    $invalid = $service->dryRun(importRequest([
        'header' => ['name', 'email', 'status'],
        'rows' => [
            ['Ada', 'not-an-email', 'active'],
            ['', 'grace@example.com', 'active'],
            ['Linus', 'linus@example.com'],
            ['Ken', 'ken@example.com', 'retired'],
        ],
        'mapping' => [],
    ]));

    expect($unknown->state)->toBe(DataImportRun::STATE_INVALID)
        ->and($unknown->rejectedRows[0]['reason'])->toContain('Unknown column')
        ->and($invalid->acceptedRows)->toBe([])
        ->and(array_column($invalid->rejectedRows, 'line'))->toBe([2, 3, 4, 5])
        ->and(implode(' ', array_column($invalid->rejectedRows, 'reason')))
        ->toContain('email')->toContain('required')->toContain('Column count')->toContain('allowed');
});

it('queues only the checksum-confirmed immutable valid run and never writes synchronously', function () {
    [$service, $adapter, , $queue, , $activity] = importService();
    $run = $service->dryRun(importRequest());

    $queued = $service->commit(7, $run->id, $run->inputHash);

    expect($queued->state)->toBe(DataImportRun::STATE_COMMITTING)
        ->and($queued->jobId)->toBe(91)
        ->and($queue->runs[0]->acceptedRows)->toBe($run->acceptedRows)
        ->and($adapter->created)->toBe([])
        ->and(array_column($activity->events, 'kind'))->toBe(['data.import.validated', 'data.import.queued'])
        ->and(fn () => $service->commit(7, $run->id, $run->inputHash))
        ->toThrow(DomainException::class, 'cannot be committed')
        ->and(fn () => $service->commit(7, $run->id, hash('sha256', 'changed')))
        ->toThrow(DomainException::class);
});

it('rejects unauthorized dry-runs before persisting import payloads', function () {
    [$service, $adapter, $store] = importService(false);

    expect(fn () => $service->dryRun(importRequest()))->toThrow(DomainException::class, 'permission')
        ->and($store->runs)->toBe([])
        ->and($adapter->created)->toBe([]);
});

it('creates a new immutable dry run when stored upload mapping or unknown policy changes', function () {
    [$service] = importService();
    $initial = $service->dryRun(importRequest(['mapping' => [], 'unknown_policy' => 'reject']));

    $remapped = $service->remap(7, $initial->id, ['state' => 'status', 'notes' => ''], 'ignore');

    expect($initial->state)->toBe(DataImportRun::STATE_INVALID)
        ->and($remapped->id)->not->toBe($initial->id)
        ->and($remapped->state)->toBe(DataImportRun::STATE_VALID)
        ->and($remapped->acceptedRows[0])->toBe([
            'name' => 'Ada Lovelace', 'email' => 'ada@example.com', 'status' => 'active',
        ]);
});

it('commits only accepted dry-run rows through the adapter in bounded batches', function () {
    [$service, $adapter, $store, , $sources, $activity] = importService();
    $run = $service->dryRun(importRequest([
        'header' => ['name', 'email'],
        'rows' => [['Ada', 'ada@example.com'], ['', 'bad@example.com'], ['Grace', 'grace@example.com']],
        'mapping' => [],
    ]));
    $run = $service->commit(7, $run->id, $run->inputHash);
    $now = new DateTimeImmutable('2026-07-04T11:00:00+00:00');
    $job = BoundedJob::queued(DataImportJobHandler::KIND, 7, 2, $run->inputHash, $now)->withId(91)->start($now);
    $handler = new DataImportJobHandler($sources, $store, new ActivityService($activity));

    $result = $handler->handle($job, 25);

    expect($result->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($result->succeeded)->toBe(2)
        ->and($adapter->created)->toBe([
            ['name' => 'Ada', 'email' => 'ada@example.com'],
            ['name' => 'Grace', 'email' => 'grace@example.com'],
        ])
        ->and($store->find($run->id)?->state)->toBe(DataImportRun::STATE_COMPLETED)
        ->and($activity->events[array_key_last($activity->events)]->kind)->toBe('data.import.completed');
});

it('writes a formula-safe downloadable rejected-row CSV report', function () {
    [$service] = importService();
    $run = $service->dryRun(importRequest([
        'header' => ['name', 'email'],
        'rows' => [['=2+2', 'bad-email']],
        'mapping' => [],
    ]));

    $csv = (new ImportReportWriter())->write($run);
    $lines = array_values(array_filter(preg_split('/\R/', trim($csv)) ?: []));
    $row = str_getcsv($lines[1]);

    expect(str_getcsv($lines[0]))->toBe(['line', 'reason', 'name', 'email'])
        ->and($row[0])->toBe('2')
        ->and($row[2])->toBe("'=2+2")
        ->and($row[3])->toBe('bad-email');
});
