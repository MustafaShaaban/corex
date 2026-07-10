<?php

/**
 * Submission workflow tests for spec 068 T093 / FR-047, FR-050, and FR-051.
 *
 * @package Corex\Tests\Unit\Submissions
 */

declare(strict_types=1);

use Corex\Config\Submissions\SubmissionAccessScope;
use Corex\Config\Submissions\SubmissionAssignment;
use Corex\Config\Submissions\SubmissionTimelineStore;
use Corex\Config\Submissions\SubmissionWorkflowService;
use Corex\Config\Submissions\SubmissionWorkflowStore;

function workflowStore(array $record): SubmissionWorkflowStore
{
    return new class($record) implements SubmissionWorkflowStore {
        /** @param array<string,mixed> $record */
        public function __construct(public array $record)
        {
        }

        public function findWorkflow(int $id): ?array
        {
            return (int) $this->record['id'] === $id ? $this->record : null;
        }

        public function updateWorkflow(int $id, array $changes, string $expectedUpdatedAt): array
        {
            if ($expectedUpdatedAt !== $this->record['updated_at']) {
                throw new DomainException('The submission changed after it was loaded.');
            }

            $this->record = [...$this->record, ...$changes, 'updated_at' => '2026-07-04T12:01:00+00:00'];

            return $this->record;
        }

        public function addWorkflowNote(int $id, int $authorId, string $body, string $visibility): array
        {
            return [
                'id' => 88,
                'submission_id' => $id,
                'author_id' => $authorId,
                'body' => $body,
                'visibility' => $visibility,
                'created_at' => '2026-07-04T12:02:00+00:00',
            ];
        }
    };
}

function timelineStore(): SubmissionTimelineStore
{
    return new class() implements SubmissionTimelineStore {
        /** @var list<array<string,mixed>> */
        public array $events = [];

        public function append(int $submissionId, string $stage, string $outcome, array $summary): array
        {
            $event = compact('submissionId', 'stage', 'outcome', 'summary');
            $this->events[] = $event;

            return $event;
        }

        public function forSubmission(int $submissionId, bool $includeRestricted): array
        {
            return $this->events;
        }
    };
}

function workflowRecord(): array
{
    return [
        'id' => 12,
        'status' => 'new',
        'read_at' => null,
        'read_by' => null,
        'owner_type' => 'none',
        'owner_key' => '',
        'updated_at' => '2026-07-04T12:00:00+00:00',
    ];
}

it('changes supported status and appends a status timeline event', function () {
    $store = workflowStore(workflowRecord());
    $timeline = timelineStore();
    $service = new SubmissionWorkflowService($store, $timeline);
    $scope = new SubmissionAccessScope(7, true);

    $updated = $service->changeStatus($scope, 12, 'in_progress', '2026-07-04T12:00:00+00:00');

    expect($updated['status'])->toBe('in_progress')
        ->and($timeline->events)->toHaveCount(1)
        ->and($timeline->events[0]['stage'])->toBe('status')
        ->and($timeline->events[0]['summary'])->toMatchArray(['from' => 'new', 'to' => 'in_progress', 'actor_id' => 7]);
});

it('marks a submission read and preserves the actor in state and history', function () {
    $store = workflowStore(workflowRecord());
    $timeline = timelineStore();
    $service = new SubmissionWorkflowService($store, $timeline);

    $updated = $service->markRead(new SubmissionAccessScope(7, true), 12, '2026-07-04T12:00:00+00:00');

    expect($updated['read_by'])->toBe(7)
        ->and($updated['read_at'])->not->toBeNull()
        ->and($timeline->events[0]['stage'])->toBe('read');
});

it('assigns an eligible owner and records the previous and next assignment', function () {
    $store = workflowStore(workflowRecord());
    $timeline = timelineStore();
    $service = new SubmissionWorkflowService($store, $timeline);
    $assignment = new SubmissionAssignment('team', 'sales');

    $updated = $service->assign(new SubmissionAccessScope(7, true), 12, $assignment, '2026-07-04T12:00:00+00:00');

    expect($updated)->toMatchArray(['owner_type' => 'team', 'owner_key' => 'sales'])
        ->and($timeline->events[0]['stage'])->toBe('assignment')
        ->and($timeline->events[0]['summary']['to'])->toBe('team:sales');
});

it('adds an attributed team note and records a note event without its personal body', function () {
    $timeline = timelineStore();
    $service = new SubmissionWorkflowService(workflowStore(workflowRecord()), $timeline);

    $note = $service->addNote(new SubmissionAccessScope(7, true), 12, 'Call after 3pm', 'corex-team');

    expect($note)->toMatchArray(['author_id' => 7, 'body' => 'Call after 3pm', 'visibility' => 'corex-team'])
        ->and($timeline->events[0]['stage'])->toBe('note')
        ->and($timeline->events[0]['summary'])->not->toHaveKey('body');
});

it('rejects stale writes, unsupported statuses, and restricted notes without permission', function () {
    $service = new SubmissionWorkflowService(workflowStore(workflowRecord()), timelineStore());
    $limited = new SubmissionAccessScope(7, true, canViewRestrictedNotes: false);

    expect(fn () => $service->changeStatus($limited, 12, 'deleted', '2026-07-04T12:00:00+00:00'))
        ->toThrow(InvalidArgumentException::class, 'status')
        ->and(fn () => $service->markRead($limited, 12, 'stale'))
        ->toThrow(DomainException::class, 'changed')
        ->and(fn () => $service->addNote($limited, 12, 'private', 'restricted'))
        ->toThrow(DomainException::class, 'restricted');
});
