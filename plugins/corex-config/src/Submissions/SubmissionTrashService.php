<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Retention\SubmissionRetentionTrash;
use Corex\Mail\SubmissionEmailRecords;
use DateInterval;
use DateTimeImmutable;
use DomainException;

/**
 * Moves submissions to the trash and back, and deletes them for good from it (spec 105, US1 and
 * US2). It is the only thing that does.
 *
 * Each action is checked against what the person may see, written into every submission's own
 * history while it has one, and recorded once in the activity stream: who, when, how many.
 * Nothing a visitor submitted is put in that record.
 */
final readonly class SubmissionTrashService implements SubmissionRetentionTrash
{
    /** Why a submission was not deleted: it is not in the trash, or not this person's to see. */
    public const NOT_IN_TRASH = 'not_in_trash';

    /** Why a submission was not deleted: a file uploaded with it could not be removed. */
    public const FILE_REMAINS = 'file_remains';

    public function __construct(
        private SubmissionWorkflowStore $submissions,
        private SubmissionTrashStore $trash,
        private SubmissionTimelineStore $timeline,
        private ActivityService $activity,
        private SubmissionEmailRecords $emails,
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
            $this->moveToTrash($scope, $id, $via);
        }

        $this->record($scope, 'submission.trashed', array_keys($versions), ['via' => $via]);
    }

    /**
     * The retention panel's "Move to trash": what is due, and this person's to act on.
     *
     * One that is not theirs is left where it is and the rest are moved. A selection in the inbox
     * is refused as a whole for that, because the person chose those rows; here they chose
     * "everything that is due", and nothing was shown to them that could have changed since.
     */
    public function trashForRetention(SubmissionAccessScope $scope, array $ids): int
    {
        $moved = [];
        foreach ($ids as $id) {
            $record = $this->submissions->findWorkflow($id);
            if ($record === null || ! $scope->allows($record)) {
                continue;
            }
            $this->moveToTrash($scope, $id, SubmissionTrashStore::VIA_RETENTION);
            $moved[] = $id;
        }

        if ($moved !== []) {
            $this->record($scope, 'submission.trashed', $moved, ['via' => SubmissionTrashStore::VIA_RETENTION]);
        }

        return count($moved);
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
     * Delete trashed submissions for good: each one's uploaded files, then the copies of its
     * emails, then the submission and everything stored on it (FR-011).
     *
     * One that cannot be deleted does not stop the others (FR-015). A submission whose file could
     * not be removed is left in the trash: deleting it would lose the only record of where that
     * file is (FR-016).
     *
     * @param list<int> $ids
     *
     * @return array{deleted:list<int>,failed:list<array{id:int,reason:string}>}
     *
     * @throws DomainException When this person may not delete permanently. Nothing is deleted then.
     */
    public function delete(SubmissionAccessScope $scope, array $ids): array
    {
        if (! $scope->canDeletePermanently) {
            throw new DomainException('This actor may not delete submissions permanently.');
        }

        $deleted = [];
        $failed = [];
        $forms = [];
        foreach ($ids as $id) {
            $record = $this->trashed($scope, $id);
            $reason = $record === null ? self::NOT_IN_TRASH : $this->erase($id);
            if ($reason !== '') {
                $failed[] = ['id' => $id, 'reason' => $reason];

                continue;
            }
            $deleted[] = $id;
            $forms[] = (string) ($record['form'] ?? '');
        }

        if ($deleted !== []) {
            $this->record($scope, 'submission.deleted', $deleted, [
                'forms' => array_values(array_unique(array_filter($forms))),
                'by' => 'person',
                'not_deleted' => count($failed),
            ]);
        }

        return ['deleted' => $deleted, 'failed' => $failed];
    }

    /**
     * Delete trashed submissions because the trash kept them as long as it keeps anything
     * (FR-017). The same removal as a person's, recorded as the trash's own.
     *
     * @param list<int> $ids
     *
     * @return int How many were deleted.
     */
    public function expire(array $ids): int
    {
        $deleted = [];
        $forms = [];
        foreach ($ids as $id) {
            $record = $this->trash->findTrashed($id);
            if ($record !== null && $this->erase($id) === '') {
                $deleted[] = $id;
                $forms[] = (string) ($record['form'] ?? '');
            }
        }
        if ($deleted !== []) {
            $this->recordAs(ActivityEvent::ACTOR_CRON, 0, 'CoreX retention', 'submission.deleted', $deleted, [
                'forms' => array_values(array_unique(array_filter($forms))),
                'by' => 'expiry',
                'not_deleted' => count($ids) - count($deleted),
            ]);
        }

        return count($deleted);
    }

    /**
     * Remove what is tied to a submission and stored apart from it: its uploaded files, and the
     * copies of its emails. For a submission that something else is deleting (FR-020).
     *
     * @return bool False when a file could not be removed.
     */
    public function forgetTiedData(int $id): bool
    {
        foreach ($this->trash->uploadsOf($id) as $attachmentId) {
            if (! $this->trash->forgetUpload($attachmentId)) {
                return false;
            }
        }
        $this->emails->forget($this->trash->emailAttemptsOf($id));

        return true;
    }

    /**
     * Remove one trashed submission and what is tied to it. Returns why it was not, or ''.
     */
    private function erase(int $id): string
    {
        if (! $this->forgetTiedData($id)) {
            return self::FILE_REMAINS;
        }
        $this->trash->delete($id);

        return '';
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

    private function moveToTrash(SubmissionAccessScope $scope, int $id, string $via): void
    {
        $this->trash->trash($id, $scope->actorId, $via);
        $this->timeline->append($id, 'trash', 'success', ['actor_id' => $scope->actorId, 'via' => $via]);
    }

    /**
     * @param list<int>           $ids
     * @param array<string,mixed> $context
     */
    private function record(SubmissionAccessScope $scope, string $kind, array $ids, array $context): void
    {
        $this->recordAs(ActivityEvent::ACTOR_USER, $scope->actorId, 'User #' . $scope->actorId, $kind, $ids, $context);
    }

    /**
     * @param list<int>           $ids
     * @param array<string,mixed> $context
     */
    private function recordAs(string $actorKind, int $actorId, string $actorLabel, string $kind, array $ids, array $context): void
    {
        $one = count($ids) === 1;
        $now = new DateTimeImmutable('now');

        $this->activity->record(
            actorId: $actorId,
            actorKind: $actorKind,
            actorLabel: $actorLabel,
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
