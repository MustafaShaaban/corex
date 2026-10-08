<?php

/**
 * Bounded bulk preview/apply tests for spec 068 T097 / FR-048 and FR-058.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionBulkPreview;
use Corex\Config\Submissions\SubmissionBulkPreviewStore;
use Corex\Config\Submissions\SubmissionBulkService;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionTrashService;
use Corex\Config\Submissions\SubmissionWorkflowService;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Tests\Support\InMemorySubmissionTrash;
use Corex\Tests\Support\RecordingActivityRepository;
use Corex\Tests\Support\RecordingSubmissionEmailRecords;

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

/**
 * The trash service over the same records: what leaves the inbox fake goes to a trash beside it.
 *
 * @param SubmissionWorkflowStore $records The fake above: its public `$records` is the inbox.
 */
function bulkTrash(
    SubmissionWorkflowStore $records,
    ?SubmissionTimelineStore $timeline = null,
    ?ActivityRepository $activity = null,
    ?InMemorySubmissionTrash $trash = null,
): SubmissionTrashService {
    return new SubmissionTrashService(
        $records,
        $trash ?? new InMemorySubmissionTrash($records),
        $timeline ?? bulkTimeline(),
        new ActivityService($activity ?? new RecordingActivityRepository()),
        new RecordingSubmissionEmailRecords(),
    );
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
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore(), bulkTrash($records));
    $scope = new SubmissionAccessScope(7, false, ['sales']);

    $preview = $service->preview($scope, 'mark_spam', [11, 10, 10]);

    expect($preview->token)->toBe('bulk-token')
        ->and($preview->submissionIds)->toBe([10, 11])
        ->and($preview->count())->toBe(2)
        ->and($preview->action)->toBe('mark_spam');
});

it('rejects an inaccessible member instead of silently applying a partial selection', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore(), bulkTrash($records));

    expect(fn () => $service->preview(new SubmissionAccessScope(7, false, ['sales']), 'archive', [10, 12]))
        ->toThrow(DomainException::class, 'unavailable');
});

it('applies the consumed preview exactly once and records each mutation', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $timeline = bulkTimeline();
    $previews = bulkPreviewStore();
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, $timeline), $records, $previews, bulkTrash($records));
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
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore(), bulkTrash($records));
    $scope = new SubmissionAccessScope(7, true);

    expect(fn () => $service->preview($scope, 'archive', []))->toThrow(InvalidArgumentException::class, 'selection')
        ->and(fn () => $service->preview($scope, 'archive', range(1, 101)))->toThrow(InvalidArgumentException::class, '100')
        // 'delete' stood here until spec 105 made deleting for good an action of the trash.
        ->and(fn () => $service->preview($scope, 'purge', [10]))->toThrow(InvalidArgumentException::class, 'action');
});

// Spec 105, US1: several submissions go to the trash, and come back, as one bulk action each.
it('moves a previewed selection to the trash, and records it once', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $timeline = bulkTimeline();
    $activity = new RecordingActivityRepository();
    $service = new SubmissionBulkService(
        new SubmissionWorkflowService($records, $timeline),
        $records,
        bulkPreviewStore(),
        bulkTrash($records, $timeline, $activity),
    );
    $scope = new SubmissionAccessScope(7, false, ['sales']);

    $result = $service->apply($scope, $service->preview($scope, 'trash', [10, 11])->token);

    expect($result)->toBe(['matched' => 2, 'updated' => 2, 'failed' => 0])
        ->and(array_keys($records->records))->toBe([12])
        ->and(array_column($timeline->events, 'stage'))->toBe(['trash', 'trash'])
        ->and($activity->events)->toHaveCount(1)
        ->and($activity->events[0]->kind)->toBe('submission.trashed')
        ->and($activity->events[0]->context['count'])->toBe(2);
});

it('restores a previewed selection from the trash, and refuses one that is not in it', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $trash = bulkTrash($records);
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore(), $trash);
    $scope = new SubmissionAccessScope(7, false, ['sales']);
    $trash->trash($scope, [10 => 'v1', 11 => 'v2']);

    // 12 is in the inbox, and another team's: it is not in this person's trash either way.
    expect(fn () => $service->preview($scope, 'restore', [10, 12]))->toThrow(DomainException::class, 'unavailable');

    $service->apply($scope, $service->preview($scope, 'restore', [10, 11])->token);

    expect(array_keys($records->records))->toEqualCanonicalizing([10, 11, 12])
        ->and($records->records[10]['status'])->toBe('new')
        ->and($trash->trashed($scope, 10))->toBeNull();
});

it('trashes nothing when one of the selection changed after the preview', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore(), bulkTrash($records));
    $scope = new SubmissionAccessScope(7, false, ['sales']);
    $token = $service->preview($scope, 'trash', [10, 11])->token;
    $records->records[11]['updated_at'] = 'v2-later';

    expect(fn () => $service->apply($scope, $token))->toThrow(DomainException::class, 'changed')
        ->and(array_keys($records->records))->toBe([10, 11, 12]);
});

// Spec 105, US2: deleting for good is a bulk action of the trash, for somebody who may.
it('deletes a previewed selection for good, and says which it could not', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $trash = new InMemorySubmissionTrash($records);
    $trash->uploads = [10 => [501], 11 => [502]];
    $trash->unremovable = [502];
    $service = new SubmissionBulkService(
        new SubmissionWorkflowService($records, bulkTimeline()),
        $records,
        bulkPreviewStore(),
        $trashing = bulkTrash($records, trash: $trash),
    );
    $scope = new SubmissionAccessScope(7, false, ['sales'], canDeletePermanently: true);
    $trashing->trash($scope, [10 => 'v1', 11 => 'v2']);

    $result = $service->apply($scope, $service->preview($scope, 'delete', [10, 11])->token);

    expect($result)->toBe([
        'matched' => 2,
        'updated' => 1,
        'failed' => 1,
        'failures' => [['id' => 11, 'reason' => 'file_remains']],
    ])
        ->and($trash->deleted)->toBe([10])
        ->and(array_keys($trash->records))->toBe([11]);
});

it('does not preview a permanent delete for somebody who may not, or of what is still in the inbox', function () {
    $records = bulkWorkflowStore(bulkRecords());
    $trashing = bulkTrash($records);
    $service = new SubmissionBulkService(new SubmissionWorkflowService($records, bulkTimeline()), $records, bulkPreviewStore(), $trashing);
    $manager = new SubmissionAccessScope(7, false, ['sales']);
    $administrator = new SubmissionAccessScope(7, false, ['sales'], canDeletePermanently: true);
    $trashing->trash($manager, [10 => 'v1']);

    expect(fn () => $service->preview($manager, 'delete', [10]))->toThrow(DomainException::class, 'may not')
        // 11 was never trashed: a submission is trashed first (FR-009).
        ->and(fn () => $service->preview($administrator, 'delete', [11]))->toThrow(DomainException::class, 'unavailable');
});
