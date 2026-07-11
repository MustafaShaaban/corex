<?php

/**
 * Submission export scope/privacy/audit tests for spec 068 T099 / FR-053-FR-055.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionExportJobQueue;
use Corex\Config\Submissions\SubmissionExportJobHandler;
use Corex\Config\Submissions\SubmissionExportRequest;
use Corex\Config\Submissions\SubmissionExportRun;
use Corex\Config\Submissions\SubmissionExportService;
use Corex\Config\Submissions\SubmissionExportSource;
use Corex\Config\Submissions\SubmissionExportStore;
use Corex\Config\Submissions\SubmissionExportCsvWriter;
use Corex\Config\Submissions\SubmissionAccessPolicy;
use Corex\Config\Submissions\SubmissionInboxQuery;
use Corex\Config\Submissions\SubmissionInboxReader;
use Corex\Jobs\BoundedJob;

function exportReader(array $records): SubmissionInboxReader
{
    return new class($records) implements SubmissionInboxReader {
        public function __construct(private array $records)
        {
        }

        public function queryInbox(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
        {
            $items = array_values(array_filter($this->records, fn (array $record): bool =>
                $scope->allows($record) && ($query->includeTest || ! $record['is_test'])));

            return ['items' => array_slice($items, 0, $query->perPage), 'total' => count($items)];
        }

        public function findInbox(int $id, SubmissionAccessScope $scope): ?array
        {
            $record = $this->records[$id] ?? null;

            return is_array($record) && $scope->allows($record) ? $record : null;
        }
    };
}

function exportStore(): SubmissionExportStore
{
    return new class() implements SubmissionExportStore {
        /** @var array<int,SubmissionExportRun> */
        public array $runs = [];
        /** @var array<int,string> */
        public array $artifacts = [];

        public function create(SubmissionExportRun $run): SubmissionExportRun
        {
            $stored = $run->withId(count($this->runs) + 1);
            $this->runs[$stored->id] = $stored;

            return $stored;
        }

        public function attachJob(int $runId, int $jobId): SubmissionExportRun
        {
            return $this->runs[$runId] = $this->runs[$runId]->withJob($jobId);
        }

        public function find(int $runId): ?SubmissionExportRun
        {
            return $this->runs[$runId] ?? null;
        }

        public function findByHash(string $inputHash): ?SubmissionExportRun
        {
            foreach ($this->runs as $run) {
                if ($run->inputHash === $inputHash) {
                    return $run;
                }
            }

            return null;
        }

        public function history(SubmissionAccessScope $scope, int $limit): array
        {
            return array_values(array_filter($this->runs, fn (SubmissionExportRun $run): bool =>
                $scope->manageAll || $run->actorId === $scope->actorId));
        }

        public function saveArtifact(int $runId, string $csv, int $recordCount): void
        {
            $this->artifacts[$runId] = $csv;
        }

        public function artifact(int $runId): ?string
        {
            return $this->artifacts[$runId] ?? null;
        }
    };
}

function exportQueue(): SubmissionExportJobQueue
{
    return new class() implements SubmissionExportJobQueue {
        public function enqueue(SubmissionExportRun $run): int
        {
            return 900 + $run->id;
        }
    };
}

function exportActivity(): array
{
    $repository = new class() implements ActivityRepository {
        public array $events = [];

        public function append(ActivityEvent $event): ActivityEvent
        {
            $this->events[] = $event;

            return $event->withId(count($this->events));
        }

        public function find(int $id): ?ActivityEvent
        {
            return $this->events[$id - 1] ?? null;
        }

        public function query(array $filters = [], int $page = 1, int $perPage = 20): array
        {
            return $this->events;
        }

        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
        {
            return 0;
        }
    };

    return [new ActivityService($repository), $repository];
}

function exportRecords(): array
{
    return [
        20 => ['id' => 20, 'owner_type' => 'team', 'owner_key' => 'sales', 'is_test' => false],
        21 => ['id' => 21, 'owner_type' => 'team', 'owner_key' => 'sales', 'is_test' => true],
        22 => ['id' => 22, 'owner_type' => 'team', 'owner_key' => 'legal', 'is_test' => false],
    ];
}

it('queues an exact selected export and excludes marked tests by default', function () {
    [$activity, $events] = exportActivity();
    $store = exportStore();
    $service = new SubmissionExportService(exportReader(exportRecords()), $store, exportQueue(), $activity);
    $scope = new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: true);
    $request = SubmissionExportRequest::from([
        'scope' => 'selected',
        'selected_ids' => [20],
        'columns' => ['submitted_fields', 'consent_snapshot'],
        'personal_data_acknowledged' => true,
    ]);

    $run = $service->request($scope, $request);

    expect($run->id)->toBe(1)
        ->and($run->jobId)->toBe(901)
        ->and($run->selectedIds)->toBe([20])
        ->and($run->includeTest)->toBeFalse()
        ->and($events->events)->toHaveCount(1)
        ->and($events->events[0]->kind)->toBe('submission.export.queued')
        ->and($events->events[0]->context)->toMatchArray(['scope' => 'selected', 'record_count' => 1]);
});

it('requires personal-data permission and explicit acknowledgement', function () {
    [$activity] = exportActivity();
    $service = new SubmissionExportService(exportReader(exportRecords()), exportStore(), exportQueue(), $activity);
    $request = SubmissionExportRequest::from([
        'scope' => 'selected',
        'selected_ids' => [20],
        'columns' => ['submitted_fields'],
    ]);

    expect(fn () => $service->request(new SubmissionAccessScope(7, true), $request))
        ->toThrow(DomainException::class, 'personal data')
        ->and(fn () => $service->request(
            new SubmissionAccessScope(7, true, canExportPersonalData: true),
            $request,
        ))->toThrow(DomainException::class, 'acknowledge');
});

it('rejects inaccessible or test records without leaking which selection member failed', function () {
    [$activity] = exportActivity();
    $service = new SubmissionExportService(exportReader(exportRecords()), exportStore(), exportQueue(), $activity);
    $scope = new SubmissionAccessScope(7, false, ['sales'], canExportPersonalData: true);

    foreach ([[20, 22], [20, 21]] as $ids) {
        $request = SubmissionExportRequest::from([
            'scope' => 'selected',
            'selected_ids' => $ids,
            'columns' => ['submitted_fields'],
            'personal_data_acknowledged' => true,
        ]);
        expect(fn () => $service->request($scope, $request))->toThrow(DomainException::class, 'unavailable');
    }
});

it('returns permission-scoped export history', function () {
    [$activity] = exportActivity();
    $store = exportStore();
    $service = new SubmissionExportService(exportReader(exportRecords()), $store, exportQueue(), $activity);
    $scope = new SubmissionAccessScope(7, true, canExportPersonalData: true);
    $request = SubmissionExportRequest::from([
        'scope' => 'accessible',
        'columns' => ['submitted_fields'],
        'personal_data_acknowledged' => true,
    ]);
    $service->request($scope, $request);

    expect($service->history($scope))->toHaveCount(1)
        ->and($service->history(new SubmissionAccessScope(8, false)))->toBe([]);
});

it('runs a bounded selected export into a formula-safe protected artifact', function () {
    $scope = new SubmissionAccessScope(7, true, canExportPersonalData: true);
    $source = new class($scope) implements SubmissionExportSource {
        public array $marked = [];

        public function __construct(private SubmissionAccessScope $scope)
        {
        }

        public function queryInbox(SubmissionInboxQuery $query, SubmissionAccessScope $scope): array
        {
            return ['items' => [], 'total' => 0];
        }

        public function findInbox(int $id, SubmissionAccessScope $scope): ?array
        {
            return $id === 20 ? [
                'id' => 20,
                'flow' => 'Contact',
                'created_at' => '2026-07-04',
                'is_test' => false,
                'values' => ['name' => '=2+2'],
            ] : null;
        }

        public function markExported(array $submissionIds, string $exportedAt): void
        {
            $this->marked = $submissionIds;
        }
    };
    $policy = new class($scope) implements SubmissionAccessPolicy {
        public function __construct(private SubmissionAccessScope $scope)
        {
        }

        public function scopeFor(int $actorId): ?SubmissionAccessScope
        {
            return $actorId === $this->scope->actorId ? $this->scope : null;
        }
    };
    $request = SubmissionExportRequest::from([
        'scope' => 'selected',
        'selected_ids' => [20],
        'columns' => ['submitted_fields'],
        'personal_data_acknowledged' => true,
    ]);
    $store = exportStore();
    $run = $store->create(SubmissionExportRun::queued(7, $request, 1));
    $now = new DateTimeImmutable('2026-07-04T12:00:00+00:00');
    $job = BoundedJob::queued(SubmissionExportJobHandler::KIND, 7, 1, $run->inputHash, $now)->withId(9)->start($now);

    $completed = (new SubmissionExportJobHandler($source, $policy, $store, new SubmissionExportCsvWriter()))
        ->handle($job, 100);

    expect($completed->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($completed->resultArtifact)->toBe('submission-export:' . $run->id)
        ->and($store->artifact($run->id))->toContain('submitted_fields')
        ->and($store->artifact($run->id))->toContain('=2+2')
        ->and($store->artifact($run->id))->not->toContain("\n=2+2")
        ->and($source->marked)->toBe([20]);
});
