<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use DateInterval;
use DateTimeImmutable;
use DomainException;

/**
 * Moves submissions to the trash and back (spec 105, US1), and is the only thing that does.
 *
 * Each action is checked against what the person may see, written into every submission's own
 * history, and recorded once in the activity stream: who, when, how many. Nothing a visitor
 * submitted is put in that record.
 */
final readonly class SubmissionTrashService
{
    public function __construct(
        private SubmissionWorkflowStore $submissions,
        private SubmissionTrashStore $trash,
        private SubmissionTimelineStore $timeline,
        private ActivityService $activity,
    ) {
    }

    /**
     * @param array<int,string> $versions Each submission's id, with the `updated_at` it was shown with.
     *
     * @throws DomainException When one is not the person's to act on, or changed since it was shown.
     *                         Nothing is trashed then.
     */
    public function trash(SubmissionAccessScope $scope, array $versions, string $via = SubmissionTrashStore::VIA_INBOX): void
    {
        foreach ($versions as $id => $version) {
            $this->assertCurrent($scope, $this->submissions->findWorkflow($id), $version);
        }
        foreach (array_keys($versions) as $id) {
            $this->trash->trash($id, $scope->actorId, $via);
            $this->timeline->append($id, 'trash', 'success', ['actor_id' => $scope->actorId, 'via' => $via]);
        }

        $this->record($scope, 'submission.trashed', array_keys($versions), ['via' => $via]);
    }

    /**
     * @param list<int> $ids
     *
     * @throws DomainException When one is not in the trash, or not the person's to act on. Nothing
     *                         is restored then.
     */
    public function restore(SubmissionAccessScope $scope, array $ids): void
    {
        foreach ($ids as $id) {
            if ($this->trashed($scope, $id) === null) {
                throw new DomainException('The submission is unavailable to this actor.');
            }
        }
        foreach ($ids as $id) {
            $this->trash->restore($id);
            $this->timeline->append($id, 'restore', 'success', ['actor_id' => $scope->actorId]);
        }

        $this->record($scope, 'submission.restored', $ids, []);
    }

    /**
     * A trashed submission this person may see, or null.
     *
     * @return array<string,mixed>|null
     */
    public function trashed(SubmissionAccessScope $scope, int $id): ?array
    {
        $record = $this->trash->findTrashed($id);

        return $record !== null && $scope->allows($record) ? $record : null;
    }

    /** @param array<string,mixed>|null $record */
    private function assertCurrent(SubmissionAccessScope $scope, ?array $record, string $version): void
    {
        if ($record === null || ! $scope->allows($record)) {
            throw new DomainException('The submission is unavailable to this actor.');
        }
        if (! hash_equals((string) $record['updated_at'], $version)) {
            throw new DomainException('The submission changed after it was loaded.');
        }
    }

    /**
     * @param list<int>           $ids
     * @param array<string,mixed> $context
     */
    private function record(SubmissionAccessScope $scope, string $kind, array $ids, array $context): void
    {
        $one = count($ids) === 1;
        $now = new DateTimeImmutable('now');

        $this->activity->record(
            actorId: $scope->actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: 'User #' . $scope->actorId,
            area: ActivityEvent::AREA_SUBMISSIONS,
            kind: $kind,
            targetType: 'submission',
            targetId: $one ? (string) $ids[0] : '',
            targetLabel: $one ? 'Submission #' . $ids[0] : count($ids) . ' submissions',
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => $kind, 'args' => ['count' => count($ids)]],
            context: ['count' => count($ids), 'submission_ids' => $ids] + $context,
            sensitivity: ActivityEvent::SENSITIVITY_RESTRICTED,
            retentionUntil: $now->add(new DateInterval('P1Y')),
            occurredAt: $now,
        );
    }
}
