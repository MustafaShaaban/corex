<?php

/**
 * Migration preview/snapshot/transaction/rollback/history tests (spec 068 T123 / FR-069).
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
use Corex\Config\Data\DataRegistry;
use Corex\Config\Data\DataSource;
use Corex\Config\Data\DataSourceService;
use Corex\Config\DataModels\MigrationAwareDataSource;
use Corex\Config\DataModels\MigrationDefinition;
use Corex\Config\DataModels\MigrationJobHandler;
use Corex\Config\DataModels\MigrationJobQueue;
use Corex\Config\DataModels\MigrationPreview;
use Corex\Config\DataModels\MigrationPreviewStore;
use Corex\Config\DataModels\MigrationProvider;
use Corex\Config\DataModels\MigrationRun;
use Corex\Config\DataModels\MigrationRunStore;
use Corex\Config\DataModels\MigrationService;
use Corex\Data\DataSourceCapabilities;
use Corex\Jobs\BoundedJob;
use Corex\Operations\OperationResult;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('provides source-declared migration and rollback contracts', function () {
    expect(class_exists(Corex\Config\DataModels\MigrationService::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\MigrationDefinition::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\MigrationProvider::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\MigrationAwareDataSource::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\MigrationPreview::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\MigrationPreviewStore::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\MigrationRun::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\MigrationRunStore::class))->toBeTrue()
        ->and(interface_exists(Corex\Config\DataModels\MigrationJobQueue::class))->toBeTrue()
        ->and(class_exists(Corex\Config\DataModels\MigrationJobHandler::class))->toBeTrue();
});

/** @return array{MigrationService,MigrationProvider,MigrationRunStore,MigrationJobQueue,MigrationPreviewStore,DataSourceService,ActivityRepository} */
function migrationService(bool $allowed = true, bool $rollback = true): array
{
    $provider = new class($rollback) implements MigrationProvider {
        /** @var list<string> */ public array $calls = [];
        public bool $fail = false;
        public function __construct(private bool $rollback) {}
        public function definitions(): array
        {
            return [new MigrationDefinition(
                key: 'contacts-v2', version: '2.0.0', description: 'Add contact state index.',
                plan: ['Add state column', 'Create state index'], transactional: true,
                rollbackSupported: $this->rollback,
            )];
        }
        public function snapshot(MigrationDefinition $definition): string
        {
            $this->calls[] = 'snapshot:' . $definition->key;

            return 'snapshot-20260704';
        }
        public function execute(MigrationDefinition $definition, string $snapshotId, bool $rollback): OperationResult
        {
            $this->calls[] = ($rollback ? 'rollback:' : 'apply:') . $definition->key . ':' . $snapshotId . ':transactional';
            $now = new DateTimeImmutable('2026-07-04T13:00:00+00:00');

            return new OperationResult(
                '123e4567-e89b-42d3-a456-426614174000',
                $this->fail ? OperationResult::STATE_FAILED : OperationResult::STATE_COMPLETED,
                $this->fail ? 'Migration failed.' : 'Migration completed.',
                $this->fail ? [['code' => 'provider_failed', 'message' => 'Migration failed.']] : [],
                [], $now, $now,
            );
        }
    };
    $source = new class($provider, $rollback) implements DataSource, CapabilityAwareDataSource, MigrationAwareDataSource {
        public function __construct(private MigrationProvider $provider, private bool $rollback) {}
        public function key(): string { return 'contacts'; }
        public function label(): string { return 'Contacts'; }
        public function columns(): array { return []; }
        public function rows(int $page, int $perPage): array { return []; }
        public function total(): int { return 0; }
        public function delete(int $id): bool { return false; }
        public function migrationProvider(): MigrationProvider { return $this->provider; }
        public function capabilities(): DataSourceCapabilities
        {
            return new DataSourceCapabilities(
                sourceKey: 'contacts', read: true, query: false, schema: true, detail: false,
                create: false, update: false, delete: false, bulkUpdate: false, bulkDelete: false,
                importDryRun: false, importCommit: false, exportCsv: false, exportXlsx: false,
                migrations: true, rollback: $this->rollback, maxPageSize: 20,
                permissionMap: ['migrations' => 'corex_manage_data_models', 'rollback' => 'corex_manage_data_models'],
            );
        }
    };
    $registry = new DataRegistry();
    $registry->register($source);
    $policy = new class($allowed) implements DataAccessPolicy {
        public function __construct(private bool $allowed) {}
        public function allows(int $actorId, string $ability): bool { return $this->allowed && $actorId === 7; }
    };
    $sources = new DataSourceService($registry, $policy);
    $previews = new class implements MigrationPreviewStore {
        /** @var array<string,MigrationPreview> */ public array $items = [];
        public function issue(int $actorId, string $action, string $sourceKey, MigrationDefinition $definition, int $runId = 0): MigrationPreview
        {
            $preview = MigrationPreview::from([
                'token' => 'migration-preview-' . (count($this->items) + 1), 'actor_id' => $actorId,
                'action' => $action, 'source_key' => $sourceKey, 'definition' => $definition->toArray(),
                'run_id' => $runId, 'expires_at' => 1_900_000_000,
            ]);
            $this->items[$preview->token] = $preview;

            return $preview;
        }
        public function consume(string $token, int $actorId): ?MigrationPreview
        {
            $preview = $this->items[$token] ?? null;
            unset($this->items[$token]);

            return $preview?->actorId === $actorId ? $preview : null;
        }
    };
    $runs = new class implements MigrationRunStore {
        /** @var array<int,MigrationRun> */ public array $runs = [];
        public function create(MigrationRun $run): MigrationRun { $run = $run->withId(count($this->runs) + 1); return $this->runs[$run->id] = $run; }
        public function attachJob(int $id, int $jobId): MigrationRun { return $this->runs[$id] = $this->runs[$id]->withJob($jobId); }
        public function find(int $id): ?MigrationRun { return $this->runs[$id] ?? null; }
        public function findByHash(string $hash): ?MigrationRun { foreach ($this->runs as $run) if ($run->inputHash === $hash) return $run; return null; }
        public function finish(int $id, string $state, string $message): void { $this->runs[$id] = $this->runs[$id]->finished($state, $message); }
        public function history(int $actorId, bool $manageAll, int $limit): array { return array_slice(array_values(array_filter($this->runs, static fn (MigrationRun $run): bool => $manageAll || $run->actorId === $actorId)), 0, $limit); }
    };
    $queue = new class implements MigrationJobQueue {
        /** @var list<MigrationRun> */ public array $runs = [];
        public function enqueue(MigrationRun $run): int { $this->runs[] = $run; return count($this->runs) + 70; }
    };
    $activity = new class implements ActivityRepository {
        /** @var list<ActivityEvent> */ public array $events = [];
        public function append(ActivityEvent $event): ActivityEvent { $event = $event->withId(count($this->events) + 1); $this->events[] = $event; return $event; }
        public function find(int $id): ?ActivityEvent { return $this->events[$id - 1] ?? null; }
        public function query(array $filters = [], int $page = 1, int $perPage = 20): array { return $this->events; }
        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int { return 0; }
    };

    return [new MigrationService($sources, $previews, $runs, $queue), $provider, $runs, $queue, $previews, $sources, $activity];
}

it('catalogs source-declared pending plans and previews without changing schema', function () {
    [$service, $provider] = migrationService();

    $catalog = $service->catalog(7, 'contacts');
    $preview = $service->previewApply(7, 'contacts', 'contacts-v2');

    expect($catalog[0])->toMatchArray([
        'key' => 'contacts-v2', 'version' => '2.0.0', 'transactional' => true, 'rollback_supported' => true,
    ])->and($preview->definition->plan)->toBe(['Add state column', 'Create state index'])
        ->and($preview->productionWarning)->toBeTrue()
        ->and($provider->calls)->toBe([]);
});

it('consumes an apply preview once and snapshots before queueing immutable history', function () {
    [$service, $provider, $runs, $queue] = migrationService();
    $preview = $service->previewApply(7, 'contacts', 'contacts-v2');

    $run = $service->queue(7, $preview->token);

    expect($provider->calls)->toBe(['snapshot:contacts-v2'])
        ->and($run->snapshotId)->toBe('snapshot-20260704')
        ->and($run->state)->toBe(MigrationRun::STATE_QUEUED)
        ->and($run->jobId)->toBe(71)
        ->and($queue->runs)->toHaveCount(1)
        ->and($runs->history(7, false, 50))->toHaveCount(1)
        ->and(fn () => $service->queue(7, $preview->token))->toThrow(DomainException::class, 'expired or was already used');
});

it('executes the exact transactional plan and records completion activity', function () {
    [$service, $provider, $runs, , , $sources, $activity] = migrationService();
    $run = $service->queue(7, $service->previewApply(7, 'contacts', 'contacts-v2')->token);
    $now = new DateTimeImmutable('2026-07-04T13:00:00+00:00');
    $job = BoundedJob::queued(MigrationJobHandler::KIND, 7, 1, $run->inputHash, $now)->withId(71)->start($now);
    $handler = new MigrationJobHandler($sources, $runs, new ActivityService($activity));

    $result = $handler->handle($job, 1);

    expect($result->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($provider->calls)->toBe(['snapshot:contacts-v2', 'apply:contacts-v2:snapshot-20260704:transactional'])
        ->and($runs->find($run->id)?->state)->toBe(MigrationRun::STATE_APPLIED)
        ->and($activity->events[0]->kind)->toBe('data.migration.applied');
});

it('previews and queues rollback only when declared while reusing the original snapshot', function () {
    [$service, , $runs, , , $sources, $activity] = migrationService();
    $apply = $service->queue(7, $service->previewApply(7, 'contacts', 'contacts-v2')->token);
    $now = new DateTimeImmutable('2026-07-04T13:00:00+00:00');
    (new MigrationJobHandler($sources, $runs, new ActivityService($activity)))->handle(
        BoundedJob::queued(MigrationJobHandler::KIND, 7, 1, $apply->inputHash, $now)->withId(71)->start($now), 1,
    );

    $preview = $service->previewRollback(7, $apply->id);
    expect(fn () => $service->queue(7, $preview->token, MigrationRun::ACTION_ROLLBACK, $apply->id + 1))
        ->toThrow(DomainException::class, 'does not match');

    $preview = $service->previewRollback(7, $apply->id);
    $rollback = $service->queue(7, $preview->token, MigrationRun::ACTION_ROLLBACK, $apply->id);

    expect($rollback->action)->toBe(MigrationRun::ACTION_ROLLBACK)
        ->and($rollback->parentRunId)->toBe($apply->id)
        ->and($rollback->snapshotId)->toBe($apply->snapshotId);

    [$unsupported] = migrationService(true, false);
    $unsupportedApply = $unsupported->queue(7, $unsupported->previewApply(7, 'contacts', 'contacts-v2')->token);
    expect(fn () => $unsupported->previewRollback(7, $unsupportedApply->id))
        ->toThrow(DomainException::class, 'rollback');
});

it('retains failed migration history and the recovery snapshot', function () {
    [$service, $provider, $runs, , , $sources, $activity] = migrationService();
    $run = $service->queue(7, $service->previewApply(7, 'contacts', 'contacts-v2')->token);
    $provider->fail = true;
    $now = new DateTimeImmutable('2026-07-04T13:00:00+00:00');
    $job = BoundedJob::queued(MigrationJobHandler::KIND, 7, 1, $run->inputHash, $now)->withId(71)->start($now);

    $result = (new MigrationJobHandler($sources, $runs, new ActivityService($activity)))->handle($job, 1);

    expect($result->state)->toBe(BoundedJob::STATE_FAILED)
        ->and($runs->find($run->id)?->state)->toBe(MigrationRun::STATE_FAILED)
        ->and($runs->find($run->id)?->snapshotId)->toBe('snapshot-20260704')
        ->and($activity->events[0]->outcome)->toBe(ActivityEvent::OUTCOME_FAILURE);
});
