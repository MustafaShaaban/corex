<?php

/**
 * Bounded bulk preview/apply tests for spec 068 T097 / FR-048 and FR-058.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionBulkPreview;
use Corex\Config\Submissions\SubmissionBulkPreviewStore;
use Corex\Config\Submissions\SubmissionBulkService;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionWorkflowService;
use Corex\Config\Submissions\SubmissionWorkflowStore;

function bulkWorkflowStore(array $records): SubmissionWorkflowStore
{
    return new class($records) implements SubmissionWorkflowStore {
        /** @param array<int,array<string,mixed>> $records */
        public function __construct(public array $records)
        {
        }

        public function findWorkflow(int $id): ?array
        {
            return $this->records[$id] ?? null;
        }

        public function updateWorkflow(int $id, array $changes, string $expectedUpdatedAt): array
        {
            $record = $this->records[$id] ?? throw new DomainException('missing');
            if ($record['updated_at'] !== $expectedUpdatedAt) {
                throw new DomainException('changed');
            }
            $this->records[$id] = [...$record, ...$changes, 'updated_at' => $expectedUpdatedAt . '-next'];

            return $this->records[$id];
        }

        public function addWorkflowNote(int $id, int $authorId, string $body, string $visibility): array
        {
            throw new BadMethodCallException('Bulk actions do not add notes.');
        }
    };
}

function bulkTimeline(): SubmissionTimelineStore
{
    return new class() implements SubmissionTimelineStore {
        public array $events = [];

        public function append(int $submissionId, string $stage, string $outcome, array $summary): array
        {
            return $this->events[] = compact('submissionId', 'stage', 'outcome', 'summary');
        }

        public function forSubmission(int $submissionId, bool $includeRestricted): array
        {
            return array_values(array_filter($this->events, fn (array $event): bool => $event['submissionId'] === $submissionId));
        }
    };
}

function bulkPreviewStore(): SubmissionBulkPreviewStore
{
    return new class() implements SubmissionBulkPreviewStore {
        public ?SubmissionBulkPreview $preview = null;

        public function issue(int $actorId, string $action, array $records, array $parameters): SubmissionBulkPreview
        {
            return $this->preview = SubmissionBulkPreview::from([
                'token' => 'bulk-token',
                'actor_id' => $actorId,
                'action' => $action,
                'records' => $records,
                'parameters' => $parameters,
                'expires_at' => time() + 300,
            ]);
        }

        public function consume(string $token, int $actorId): ?SubmissionBulkPreview
        {
            if ($this->preview === null || $token !== $this->preview->token || $actorId !== $this->preview->actorId) {
                return null;
            }
            $preview = $this->preview;
            $this->preview = null;

            return $preview;
        }
    };
}

function bulkRecords(): array
{
    return [
        10 => ['id' => 10, 'status' => 'new', 'owner_type' => 'team', 'owner_key' => 'sales', 'updated_at' => 'v1'],
        11 => ['id' => 11, 'status' => 'new', 'owner_type' => 'team', 'owner_key' => 'sales', 'updated_at' => 'v2'],
        12 => ['id' => 12, 'status' => 'new', 'owner_type' => 'team', 'owner_key' => 'legal', 'updated_at' => 'v3'],
    ];
}

it('previews only an exact bounded accessible selection', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore());
    $scope = new SubmissionAccessScope(7, false, ['sales']);

    $preview = $service->preview($scope, 'mark_spam', [11, 10, 10]);

    expect($preview->token)->toBe('bulk-token')
        ->and($preview->submissionIds)->toBe([10, 11])
        ->and($preview->count())->toBe(2)
        ->and($preview->action)->toBe('mark_spam');
});

it('rejects an inaccessible member instead of silently applying a partial selection', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore());

    expect(fn () => $service->preview(new SubmissionAccessScope(7, false, ['sales']), 'archive', [10, 12]))
        ->toThrow(DomainException::class, 'unavailable');
});

it('applies the consumed preview exactly once and records each mutation', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $timeline = bulkTimeline();
    $previews = bulkPreviewStore();
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, $timeline), $records, $previews);
    $scope = new SubmissionAccessScope(7, false, ['sales']);
    $preview = $service->preview($scope, 'assign', [10, 11], ['owner_type' => 'role', 'owner_key' => 'editor']);

    $result = $service->apply($scope, $preview->token);

    expect($result)->toMatchArray(['matched' => 2, 'updated' => 2, 'failed' => 0])
        ->and($records->records[10])->toMatchArray(['owner_type' => 'role', 'owner_key' => 'editor'])
        ->and($records->records[11])->toMatchArray(['owner_type' => 'role', 'owner_key' => 'editor'])
        ->and($timeline->events)->toHaveCount(2)
        ->and(fn () => $service->apply($scope, $preview->token))
        ->toThrow(DomainException::class, 'expired');
});

it('rejects empty oversized and unsupported previews', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore());
    $scope = new SubmissionAccessScope(7, true);

    expect(fn () => $service->preview($scope, 'archive', []))->toThrow(InvalidArgumentException::class, 'selection')
        ->and(fn () => $service->preview($scope, 'archive', range(1, 101)))->toThrow(InvalidArgumentException::class, '100')
        ->and(fn () => $service->preview($scope, 'delete', [10]))->toThrow(InvalidArgumentException::class, 'action');
});
