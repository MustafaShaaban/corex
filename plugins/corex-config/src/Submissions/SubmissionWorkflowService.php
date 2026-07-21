<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Events\EventDispatcher;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Applies optimistic Inbox workflow mutations and appends privacy-safe history.
 */
final readonly class SubmissionWorkflowService
{
    public function __construct(
        private SubmissionWorkflowStore $submissions,
        private SubmissionTimelineStore $timeline,
        private ?EventDispatcher $events = null,
    ) {
    }

    /** @return array<string,mixed> */
    public function changeStatus(
        SubmissionAccessScope $scope,
        int $submissionId,
        string $status,
        string $expectedUpdatedAt,
    ): array {
        if (! in_array($status, SubmissionInboxQuery::STATUSES, true)) {
            throw new InvalidArgumentException('The submission status is invalid.');
        }

        $current = $this->accessible($scope, $submissionId);
        $updated = $this->submissions->updateWorkflow($submissionId, ['status' => $status], $expectedUpdatedAt);
        $this->timeline->append($submissionId, 'status', 'success', [
            'from' => (string) $current['status'],
            'to' => $status,
            'actor_id' => $scope->actorId,
        ]);

        return $updated;
    }

    /** @return array<string,mixed> */
    public function markRead(SubmissionAccessScope $scope, int $submissionId, string $expectedUpdatedAt): array
    {
        $this->accessible($scope, $submissionId);
        $updated = $this->submissions->updateWorkflow($submissionId, [
            'read_at' => (new DateTimeImmutable('now'))->format(DATE_ATOM),
            'read_by' => $scope->actorId,
        ], $expectedUpdatedAt);
        $this->timeline->append($submissionId, 'read', 'success', ['actor_id' => $scope->actorId]);

        return $updated;
    }

    /** @return array<string,mixed> */
    public function assign(
        SubmissionAccessScope $scope,
        int $submissionId,
        SubmissionAssignment $assignment,
        string $expectedUpdatedAt,
    ): array {
        $current = $this->accessible($scope, $submissionId);
        $updated = $this->submissions->updateWorkflow($submissionId, [
            'owner_type' => $assignment->type,
            'owner_key' => $assignment->key,
        ], $expectedUpdatedAt);
        $this->timeline->append($submissionId, 'assignment', 'success', [
            'from' => $this->assignmentLabel($current),
            'to' => $assignment->label(),
            'actor_id' => $scope->actorId,
        ]);

        // Tell the assignee (a person), independently of the inbox UI.
        $this->events?->dispatch(new SubmissionAssignedEvent(
            submissionId: $submissionId,
            assigneeType: $assignment->type,
            assigneeKey: $assignment->key,
            actorId: $scope->actorId,
        ));

        return $updated;
    }

    /** @return array<string,mixed> */
    public function addNote(
        SubmissionAccessScope $scope,
        int $submissionId,
        string $body,
        string $visibility,
    ): array {
        $this->accessible($scope, $submissionId);
        $body = trim(strip_tags($body));
        if ($body === '') {
            throw new InvalidArgumentException('A submission note cannot be empty.');
        }
        if (! in_array($visibility, ['corex-team', 'restricted'], true)) {
            throw new InvalidArgumentException('The submission note visibility is invalid.');
        }
        if ($visibility === 'restricted' && ! $scope->canViewRestrictedNotes) {
            throw new DomainException('This actor cannot create restricted submission notes.');
        }

        $note = $this->submissions->addWorkflowNote($submissionId, $scope->actorId, $body, $visibility);
        $this->timeline->append($submissionId, 'note', 'success', [
            'note_id' => (int) $note['id'],
            'visibility' => $visibility,
            'actor_id' => $scope->actorId,
        ]);

        return $note;
    }

    /** @return array<string,mixed> */
    private function accessible(SubmissionAccessScope $scope, int $submissionId): array
    {
        $record = $this->submissions->findWorkflow($submissionId);
        if ($record === null || ! $scope->allows($record)) {
            throw new DomainException('The submission is unavailable to this actor.');
        }

        return $record;
    }

    /** @param array<string,mixed> $record */
    private function assignmentLabel(array $record): string
    {
        $assignment = new SubmissionAssignment(
            (string) ($record['owner_type'] ?? 'none'),
            (string) ($record['owner_key'] ?? ''),
        );

        return $assignment->label();
    }
}
