<?php

/**
 * Mutation preview/apply tests (spec 068 T116 / FR-063, FR-064, FR-070).
 *
 * @package Corex\Tests\Unit\Data
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Data\CapabilityAwareDataSource;
use Corex\Config\Data\DataAccessPolicy;
use Corex\Config\Data\DataMutationPreview;
use Corex\Config\Data\DataMutationPreviewStore;
use Corex\Config\Data\DataMutationRequest;
use Corex\Config\Data\DataMutationService;
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\WritableDataSource;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use Corex\Data\DataWriteAdapter;
use Corex\Operations\OperationResult;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('provides the source-declared mutation boundary contracts', function () {
    expect(class_exists(Corex\Config\Data\DataMutationService::class))->toBeTrue()
        ->and(class_exists(Corex\Config\Data\DataMutationRequest::class))->toBeTrue()
        ->and(class_exists(Corex\Config\Data\DataMutationPreview::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\Data\DataMutationPreviewStore::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\Data\WritableDataSource::class))->toBeTrue();
});

/** @return array{DataMutationService,DataWriteAdapter,DataMutationPreviewStore,ActivityRepository} */
function mutationService(bool $allowed = true, bool $writable = true): array
{
    $adapter = new class implements DataWriteAdapter {
        /** @var list<array{operation:string,ids:list<int|string>,values:array<string,mixed>}> */
        public array $calls = [];

        public function create(array $values): OperationResult
        {
            $this->calls[] = ['operation' => 'create', 'ids' => [], 'values' => $values];

            return $this->result([41]);
        }

        public function update(array $recordIds, array $values): OperationResult
        {
            $this->calls[] = ['operation' => 'update', 'ids' => $recordIds, 'values' => $values];

            return $this->result($recordIds);
        }

        public function delete(array $recordIds): OperationResult
        {
            $this->calls[] = ['operation' => 'delete', 'ids' => $recordIds, 'values' => []];

            return $this->result($recordIds);
        }

        /** @param list<int|string> $ids */
        private function result(array $ids): OperationResult
        {
            $now = new DateTimeImmutable('2026-07-04T10:00:00+00:00');

            return new OperationResult(
                '123e4567-e89b-42d3-a456-426614174000',
                OperationResult::STATE_COMPLETED,
                'Mutation completed.',
                [],
                $ids,
                $now,
                $now,
            );
        }
    };

    $source = new class($adapter, $writable) implements DataSource, CapabilityAwareDataSource, FieldAwareDataSource, WritableDataSource {
        public function __construct(private DataWriteAdapter $adapter, private bool $writable) {}
        public function key(): string { return 'tickets'; }
        public function label(): string { return 'Tickets'; }
        public function columns(): array { return []; }
        public function rows(int $page, int $perPage): array { return []; }
        public function total(): int { return 0; }
        public function delete(int $id): bool { throw new RuntimeException('Legacy delete must not be used.'); }
        public function writeAdapter(): DataWriteAdapter
        {
            if (! $this->writable) {
                throw new DomainException('No write adapter.');
            }

            return $this->adapter;
        }
        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'tickets', read: true, query: true, schema: true, detail: true,
                create: $this->writable, update: $this->writable, delete: $this->writable,
                bulkUpdate: $this->writable, bulkDelete: $this->writable,
                importDryRun: false, importCommit: false, exportCsv: true, exportXlsx: false,
                migrations: false, rollback: false, maxPageSize: 100,
                permissionMap: [
                    'create' => 'corex_manage_data', 'update' => 'corex_manage_data',
                    'delete' => 'corex_manage_data', 'bulk_update' => 'corex_manage_data',
                    'bulk_delete' => 'corex_manage_data',
                ],
            );
        }
        public function fields(): array
        {
            return [
                new DataField('id', 'ID', DataField::TYPE_ID, false, false, true, ['equals'], true, DataField::PERSONAL_NONE, [], []),
                new DataField('name', 'Name', DataField::TYPE_TEXT, true, false, false, ['contains'], true, DataField::PERSONAL_NONE, [], []),
                new DataField('status', 'Status', DataField::TYPE_SELECT, false, true, false, ['equals'], true, DataField::PERSONAL_NONE, ['options' => ['open', 'closed']], []),
                new DataField('secret', 'Secret', DataField::TYPE_TEXT, false, true, true, [], false, DataField::PERSONAL_SECURITY, [], []),
            ];
        }
    };
    $registry = new DataRegistry();
    $registry->register($source);
    $policy = new class($allowed) implements DataAccessPolicy {
        public function __construct(private bool $allowed) {}
        public function allows(int $actorId, string $ability): bool { return $this->allowed && $actorId === 7; }
    };
    $previews = new class implements DataMutationPreviewStore {
        /** @var array<string,DataMutationPreview> */
        public array $issued = [];
        public function issue(DataMutationRequest $request): DataMutationPreview
        {
            $token = 'mutation-token-' . (count($this->issued) + 1);
            $preview = DataMutationPreview::from($request->toArray() + [
                'token' => $token,
                'expires_at' => 1_900_000_000,
            ]);
            $this->issued[$token] = $preview;

            return $preview;
        }
        public function consume(string $token, int $actorId): ?DataMutationPreview
        {
            $preview = $this->issued[$token] ?? null;
            unset($this->issued[$token]);

            return $preview?->actorId === $actorId ? $preview : null;
        }
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
        new DataMutationService(
            $registry,
            new DataSourceService($registry, $policy),
            $previews,
            new ActivityService($activity),
        ),
        $adapter,
        $previews,
        $activity,
    ];
}

it('previews validated create values without calling the write adapter', function () {
    [$service, $adapter] = mutationService();

    $preview = $service->preview(DataMutationRequest::from([
        'actor_id' => 7,
        'source_key' => 'tickets',
        'operation' => DataSourceCapabilities::CREATE,
        'values' => ['name' => 'Launch', 'status' => 'open'],
    ]));

    expect($preview->operation)->toBe(DataSourceCapabilities::CREATE)
        ->and($preview->values)->toBe(['name' => 'Launch', 'status' => 'open'])
        ->and($preview->recordIds)->toBe([])
        ->and($adapter->calls)->toBe([]);
});

it('applies a consumed preview once through the adapter and records authoritative activity', function () {
    [$service, $adapter, , $activity] = mutationService();
    $now = new DateTimeImmutable('2026-07-04T10:00:00+00:00');
    $preview = $service->preview(DataMutationRequest::from([
        'actor_id' => 7, 'source_key' => 'tickets', 'operation' => DataSourceCapabilities::CREATE,
        'values' => ['name' => 'Launch'],
    ]));

    $result = $service->apply(new Corex\Config\Data\DataMutationApplyRequest(7, $preview->token, 'tickets', 'Ada Admin', $now));

    expect($result->succeeded())->toBeTrue()
        ->and($result->auditEventId)->toBe(1)
        ->and($adapter->calls)->toBe([['operation' => 'create', 'ids' => [], 'values' => ['name' => 'Launch']]])
        ->and($activity->events[0]->kind)->toBe('data.record.created')
        ->and(fn () => $service->apply(new Corex\Config\Data\DataMutationApplyRequest(7, $preview->token, 'tickets', 'Ada Admin', $now)))
        ->toThrow(DomainException::class, 'expired or was already used');
});

it('rejects a preview token presented through a different source route', function () {
    [$service, $adapter] = mutationService();
    $preview = $service->preview(DataMutationRequest::from([
        'actor_id' => 7,
        'source_key' => 'tickets',
        'operation' => DataSourceCapabilities::DELETE,
        'record_ids' => [1],
    ]));
    $apply = new Corex\Config\Data\DataMutationApplyRequest(
        7,
        $preview->token,
        'other-source',
        'Ada Admin',
        new DateTimeImmutable('2026-07-04T12:00:00+00:00'),
    );

    expect(fn () => $service->apply($apply))
        ->toThrow(DomainException::class, 'does not match')
        ->and($adapter->calls)->toBe([]);
});

it('maps single and bulk update and delete previews to exact adapter calls', function () {
    [$service, $adapter] = mutationService();
    $now = new DateTimeImmutable('2026-07-04T10:00:00+00:00');
    $requests = [
        ['operation' => DataSourceCapabilities::UPDATE, 'record_ids' => [3], 'values' => ['status' => 'closed']],
        ['operation' => DataSourceCapabilities::BULK_UPDATE, 'record_ids' => [5, 4, 5], 'values' => ['status' => 'open']],
        ['operation' => DataSourceCapabilities::DELETE, 'record_ids' => [8]],
        ['operation' => DataSourceCapabilities::BULK_DELETE, 'record_ids' => [10, 9]],
    ];
    foreach ($requests as $request) {
        $preview = $service->preview(DataMutationRequest::from($request + ['actor_id' => 7, 'source_key' => 'tickets']));
        $service->apply(new Corex\Config\Data\DataMutationApplyRequest(7, $preview->token, 'tickets', 'Ada Admin', $now));
    }

    expect($adapter->calls)->toBe([
        ['operation' => 'update', 'ids' => [3], 'values' => ['status' => 'closed']],
        ['operation' => 'update', 'ids' => [4, 5], 'values' => ['status' => 'open']],
        ['operation' => 'delete', 'ids' => [8], 'values' => []],
        ['operation' => 'delete', 'ids' => [9, 10], 'values' => []],
    ]);
});

it('rejects invalid fields shapes and oversized bulk requests before issuing a preview', function () {
    [$service, $adapter, $previews] = mutationService();
    $base = ['actor_id' => 7, 'source_key' => 'tickets'];

    expect(fn () => $service->preview(DataMutationRequest::from($base + [
        'operation' => DataSourceCapabilities::CREATE, 'values' => ['status' => 'open'],
    ])))->toThrow(InvalidArgumentException::class, 'required')
        ->and(fn () => $service->preview(DataMutationRequest::from($base + [
            'operation' => DataSourceCapabilities::UPDATE, 'record_ids' => [1], 'values' => ['secret' => 'x'],
        ])))->toThrow(InvalidArgumentException::class, 'read-only')
        ->and(fn () => $service->preview(DataMutationRequest::from($base + [
            'operation' => DataSourceCapabilities::UPDATE, 'record_ids' => [1], 'values' => ['unknown' => 'x'],
        ])))->toThrow(InvalidArgumentException::class, 'unknown')
        ->and(fn () => $service->preview(DataMutationRequest::from($base + [
            'operation' => DataSourceCapabilities::DELETE, 'record_ids' => [1], 'values' => ['name' => 'x'],
        ])))->toThrow(InvalidArgumentException::class, 'values')
        ->and(fn () => $service->preview(DataMutationRequest::from($base + [
            'operation' => DataSourceCapabilities::BULK_DELETE, 'record_ids' => range(1, 101),
        ])))->toThrow(InvalidArgumentException::class, '100')
        ->and($previews->issued)->toBe([])
        ->and($adapter->calls)->toBe([]);
});

it('denies unsupported and unauthorized mutations before the adapter is resolved', function () {
    [$denied, $adapter, $previews] = mutationService(false);
    [$unsupported, $unsupportedAdapter, $unsupportedPreviews] = mutationService(true, false);
    $request = static fn (): DataMutationRequest => DataMutationRequest::from([
        'actor_id' => 7, 'source_key' => 'tickets', 'operation' => DataSourceCapabilities::CREATE,
        'values' => ['name' => 'Launch'],
    ]);

    expect(fn () => $denied->preview($request()))->toThrow(DomainException::class, 'permission')
        ->and(fn () => $unsupported->preview($request()))->toThrow(DomainException::class, 'support')
        ->and($previews->issued)->toBe([])
        ->and($unsupportedPreviews->issued)->toBe([])
        ->and($adapter->calls)->toBe([])
        ->and($unsupportedAdapter->calls)->toBe([]);
});
