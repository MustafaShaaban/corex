<?php

/**
 * Moving submissions to the trash and back (spec 105, US1; FR-005, FR-007, FR-008).
 *
 * The inbox had no way to remove a submission: reported from a client's production site, where a
 * test lead could not be taken out. The service is tested here over stores that keep their
 * records in memory; the trash as WordPress stores it is tested against WordPress.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Activity\ActivityService;
use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionTrashService;
use Corex\Config\Submissions\SubmissionTrashStore;
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Tests\Support\RecordingActivityRepository;

/**
 * An inbox and a trash that are two lists, and what stands on them.
 *
 * @return object{inbox:object,trash:object,timeline:object,activity:RecordingActivityRepository,service:SubmissionTrashService}
 */
function trashBench(): object
{
    $inbox = new class() implements SubmissionWorkflowStore {
        /** @var array<int,array<string,mixed>> */
        public array $records = [
            10 => ['id' => 10, 'status' => 'in_progress', 'owner_type' => 'team', 'owner_key' => 'sales', 'updated_at' => 'v1', 'submitter_email' => 'salma@example.com', 'values' => ['message' => 'Call me']],
            11 => ['id' => 11, 'status' => 'new', 'owner_type' => 'team', 'owner_key' => 'legal', 'updated_at' => 'v2', 'submitter_email' => 'omar@example.com', 'values' => ['message' => 'Hello']],
        ];

        public function findWorkflow(int $id): ?array
        {
            return $this->records[$id] ?? null;
        }

        public function updateWorkflow(int $id, array $changes, string $expectedUpdatedAt): array
        {
            throw new BadMethodCallException('The trash does not change a submission.');
        }

        public function addWorkflowNote(int $id, int $authorId, string $body, string $visibility): array
        {
            throw new BadMethodCallException('The trash does not add notes.');
        }
    };
    $trash = new class($inbox) implements SubmissionTrashStore {
        /** @var array<int,array<string,mixed>> */
        public array $records = [];

        public function __construct(private object $inbox)
        {
        }

        public function trash(int $id, int $actorId, string $via): void
        {
            $this->records[$id] = $this->inbox->records[$id];
            unset($this->inbox->records[$id]);
        }

        public function restore(int $id): void
        {
            $this->inbox->records[$id] = $this->records[$id];
            unset($this->records[$id]);
        }

        public function findTrashed(int $id): ?array
        {
            return $this->records[$id] ?? null;
        }
    };
    $timeline = new class() implements SubmissionTimelineStore {
        /** @var list<array<string,mixed>> */
        public array $events = [];

        public function append(int $submissionId, string $stage, string $outcome, array $summary): array
        {
            return $this->events[] = compact('submissionId', 'stage', 'outcome', 'summary');
        }

        public function forSubmission(int $submissionId, bool $includeRestricted): array
        {
            return [];
        }
    };
    $activity = new RecordingActivityRepository();

    return (object) [
        'inbox' => $inbox,
        'trash' => $trash,
        'timeline' => $timeline,
        'activity' => $activity,
        'service' => new SubmissionTrashService($inbox, $trash, $timeline, new ActivityService($activity)),
    ];
}

it('moves a submission to the trash and writes who did it into its history', function () {
    $bench = trashBench();

    $bench->service->trash(new SubmissionAccessScope(7, false, ['sales']), [10 => 'v1']);

    expect($bench->inbox->findWorkflow(10))->toBeNull()
        ->and($bench->trash->findTrashed(10)['status'])->toBe('in_progress')
        ->and($bench->timeline->events)->toBe([[
            'submissionId' => 10,
            'stage' => 'trash',
            'outcome' => 'success',
            'summary' => ['actor_id' => 7, 'via' => 'inbox'],
        ]]);
});

it('restores a trashed submission as it was, and writes that into its history too', function () {
    $bench = trashBench();
    $scope = new SubmissionAccessScope(7, false, ['sales']);
    $before = $bench->inbox->findWorkflow(10);
    $bench->service->trash($scope, [10 => 'v1']);

    $bench->service->restore($scope, [10]);

    expect($bench->inbox->findWorkflow(10))->toBe($before)
        ->and($bench->trash->findTrashed(10))->toBeNull()
        ->and(array_column($bench->timeline->events, 'stage'))->toBe(['trash', 'restore']);
});

it('records each action once in the activity stream, with nothing a visitor submitted', function () {
    $bench = trashBench();
    $scope = new SubmissionAccessScope(7, true);

    $bench->service->trash($scope, [10 => 'v1', 11 => 'v2']);
    $bench->service->restore($scope, [10]);

    [$trashed, $restored] = $bench->activity->events;
    $recorded = (string) json_encode([$trashed->context, $trashed->summary, $trashed->targetLabel, $restored->context, $restored->targetLabel]);

    expect($bench->activity->events)->toHaveCount(2)
        ->and($trashed->kind)->toBe('submission.trashed')
        ->and($trashed->actorId)->toBe(7)
        ->and($trashed->context)->toBe(['count' => 2, 'submission_ids' => [10, 11], 'via' => 'inbox'])
        ->and($trashed->targetId)->toBe('')
        ->and($restored->kind)->toBe('submission.restored')
        ->and($restored->targetId)->toBe('10')
        ->and($recorded)->not->toContain('salma@example.com')
        ->and($recorded)->not->toContain('Call me');
});

it('trashes nothing when one of them is not the person’s to see', function () {
    $bench = trashBench();

    expect(fn () => $bench->service->trash(new SubmissionAccessScope(7, false, ['sales']), [10 => 'v1', 11 => 'v2']))
        ->toThrow(DomainException::class, 'unavailable')
        ->and(array_keys($bench->inbox->records))->toBe([10, 11])
        ->and($bench->activity->events)->toBe([]);
});

it('trashes nothing when one of them changed after it was shown', function () {
    $bench = trashBench();

    expect(fn () => $bench->service->trash(new SubmissionAccessScope(7, true), [10 => 'v1', 11 => 'stale']))
        ->toThrow(DomainException::class, 'changed')
        ->and(array_keys($bench->inbox->records))->toBe([10, 11]);
});

it('does not show or restore another team’s trashed submission', function () {
    $bench = trashBench();
    $bench->service->trash(new SubmissionAccessScope(1, true), [11 => 'v2']);
    $sales = new SubmissionAccessScope(7, false, ['sales']);

    expect($bench->service->trashed($sales, 11))->toBeNull()
        ->and(fn () => $bench->service->restore($sales, [11]))->toThrow(DomainException::class, 'unavailable')
        ->and($bench->trash->findTrashed(11))->not->toBeNull();
});
