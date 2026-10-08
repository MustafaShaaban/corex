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
use Corex\Config\Submissions\SubmissionWorkflowStore;
use Corex\Tests\Support\InMemorySubmissionTrash;
use Corex\Tests\Support\RecordingActivityRepository;
use Corex\Tests\Support\RecordingSubmissionEmailRecords;

/**
 * An inbox and a trash that are two lists, and what stands on them.
 *
 * @return object{inbox:object,trash:InMemorySubmissionTrash,timeline:object,activity:RecordingActivityRepository,emails:RecordingSubmissionEmailRecords,service:SubmissionTrashService}
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
    $trash = new InMemorySubmissionTrash($inbox);
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
    $emails = new RecordingSubmissionEmailRecords();

    return (object) [
        'inbox' => $inbox,
        'trash' => $trash,
        'timeline' => $timeline,
        'activity' => $activity,
        'emails' => $emails,
        'service' => new SubmissionTrashService($inbox, $trash, $timeline, new ActivityService($activity), $emails),
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

// ---- Deleting for good (spec 105, US2) ----

/** Somebody who may see everything and delete it. */
function trashAdministrator(): SubmissionAccessScope
{
    return new SubmissionAccessScope(1, true, canDeletePermanently: true);
}

it('deletes a trashed submission with its files and the copies of its emails, in that order', function () {
    $bench = trashBench();
    $bench->service->trash(trashAdministrator(), [10 => 'v1']);
    $bench->trash->uploads = [10 => [501, 502]];
    $bench->trash->attempts = [10 => ['aaaa-1', 'aaaa-2']];

    $result = $bench->service->delete(trashAdministrator(), [10]);

    expect($result)->toBe(['deleted' => [10], 'failed' => []])
        ->and($bench->trash->forgotten)->toBe([501, 502])
        ->and($bench->emails->forgotten)->toBe([['aaaa-1', 'aaaa-2']])
        ->and($bench->trash->deleted)->toBe([10])
        ->and($bench->trash->findTrashed(10))->toBeNull();
});

it('leaves a submission in the trash when one of its files cannot be removed, and says so', function () {
    // Deleting it anyway would lose the only record of where that file is (FR-016).
    $bench = trashBench();
    $bench->service->trash(trashAdministrator(), [10 => 'v1']);
    $bench->trash->uploads = [10 => [501, 502]];
    $bench->trash->unremovable = [502];

    $result = $bench->service->delete(trashAdministrator(), [10]);

    expect($result)->toBe(['deleted' => [], 'failed' => [['id' => 10, 'reason' => 'file_remains']]])
        ->and($bench->trash->findTrashed(10))->not->toBeNull()
        ->and($bench->emails->forgotten)->toBe([])
        ->and($bench->activity->events)->toHaveCount(1); // the trashing, and nothing for a delete that did not happen
});

it('goes on past one it cannot delete, and reports each', function () {
    $bench = trashBench();
    $bench->service->trash(trashAdministrator(), [10 => 'v1', 11 => 'v2']);
    $bench->trash->uploads = [10 => [501]];
    $bench->trash->unremovable = [501];

    $result = $bench->service->delete(trashAdministrator(), [10, 11, 99]);

    expect($result['deleted'])->toBe([11])
        ->and($result['failed'])->toBe([
            ['id' => 10, 'reason' => 'file_remains'],
            ['id' => 99, 'reason' => 'not_in_trash'],
        ]);
});

it('records a deletion once, with which forms and how many, and nothing a visitor submitted', function () {
    $bench = trashBench();
    $bench->service->trash(trashAdministrator(), [10 => 'v1', 11 => 'v2']);

    $bench->service->delete(trashAdministrator(), [10, 11]);

    $deleted = $bench->activity->events[1];
    $recorded = (string) json_encode([$deleted->context, $deleted->summary, $deleted->targetLabel]);

    expect($bench->activity->events)->toHaveCount(2)
        ->and($deleted->kind)->toBe('submission.deleted')
        ->and($deleted->actorId)->toBe(1)
        ->and($deleted->context)->toMatchArray(['count' => 2, 'by' => 'person', 'not_deleted' => 0])
        ->and($recorded)->not->toContain('salma@example.com')
        ->and($recorded)->not->toContain('omar@example.com')
        ->and($recorded)->not->toContain('Call me');
});

it('deletes nothing for somebody who manages submissions and may not delete them', function () {
    $bench = trashBench();
    $manager = new SubmissionAccessScope(7, true);
    $bench->service->trash($manager, [10 => 'v1']);

    expect(fn () => $bench->service->delete($manager, [10]))->toThrow(DomainException::class, 'may not')
        ->and($bench->trash->findTrashed(10))->not->toBeNull()
        ->and($bench->trash->deleted)->toBe([]);
});

it('does not delete a submission that is still in the inbox, or another team’s', function () {
    $bench = trashBench();
    $bench->service->trash(trashAdministrator(), [11 => 'v2']);
    $sales = new SubmissionAccessScope(7, false, ['sales'], canDeletePermanently: true);

    $result = $bench->service->delete($sales, [10, 11]);

    expect($result['deleted'])->toBe([])
        ->and(array_column($result['failed'], 'reason'))->toBe(['not_in_trash', 'not_in_trash'])
        ->and($bench->inbox->findWorkflow(10))->not->toBeNull()
        ->and($bench->trash->findTrashed(11))->not->toBeNull();
});

// ---- The trash's own clock (spec 105, US3) ----

it('deletes what the trash has kept long enough, and records it as the trash’s own doing', function () {
    $bench = trashBench();
    $bench->service->trash(trashAdministrator(), [10 => 'v1', 11 => 'v2']);
    $bench->trash->uploads = [10 => [501]];
    $bench->trash->attempts = [10 => ['aaaa-1']];

    $deleted = $bench->service->expire([10, 11, 99]);

    $entry = $bench->activity->events[1];

    expect($deleted)->toBe(2)
        ->and($bench->trash->deleted)->toBe([10, 11])
        ->and($bench->trash->forgotten)->toBe([501])
        ->and($bench->emails->forgotten[0])->toBe(['aaaa-1'])
        ->and($entry->kind)->toBe('submission.deleted')
        ->and($entry->actorKind)->toBe('cron')
        ->and($entry->actorId)->toBe(0)
        ->and($entry->context)->toMatchArray(['count' => 2, 'by' => 'expiry', 'not_deleted' => 1]);
});

it('records nothing when the trash had nothing old enough', function () {
    $bench = trashBench();

    expect($bench->service->expire([]))->toBe(0)
        ->and($bench->activity->events)->toBe([]);
});

it('removes what is tied to a submission that something else is deleting', function () {
    $bench = trashBench();
    $bench->trash->uploads = [10 => [501, 502]];
    $bench->trash->attempts = [10 => ['aaaa-1']];

    expect($bench->service->forgetTiedData(10))->toBeTrue()
        ->and($bench->trash->forgotten)->toBe([501, 502])
        ->and($bench->emails->forgotten)->toBe([['aaaa-1']])
        // The submission is whoever is deleting it's to delete.
        ->and($bench->inbox->findWorkflow(10))->not->toBeNull();
});
