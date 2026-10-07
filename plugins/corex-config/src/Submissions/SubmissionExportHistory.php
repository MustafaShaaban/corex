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
 * The exports made before: what each was, handing one back, and deleting one (spec 103, US9).
 *
 * A person sees their own exports, and every export when they may manage all submissions. What can
 * be listed can be downloaded and deleted, and nothing else can.
 */
final readonly class SubmissionExportHistory
{
    /** The file is there to download. */
    public const STATE_READY = 'ready';

    /** No file was written yet: the export is still running, or it stopped. */
    public const STATE_PENDING = 'pending';

    public function __construct(
        private SubmissionExportStore $exports,
        private SubmissionExportRetention $retention,
        private SubmissionOwnerNames $owners,
        private ActivityService $activity,
    ) {
    }

    /**
     * @return list<array<string,mixed>> Each export as stored, with who made it by name, what
     *         state its file is in and when it expires.
     */
    public function entries(SubmissionAccessScope $scope, int $limit = 50): array
    {
        return array_map(
            fn (SubmissionExportRun $run): array => [
                ...$run->toArray(),
                'actor_name' => $this->nameOf($run->actorId),
                'removed_by_name' => $this->nameOf($run->removedBy),
                'state' => $this->stateOf($run),
                'expires_at' => $this->retention->expiryOf($run)->format(DATE_ATOM),
            ],
            $this->exports->history($scope, min(100, max(1, $limit))),
        );
    }

    /**
     * A finished export, for the person who made it or one who may manage every submission.
     *
     * @return array{name:string,content_type:string,path:?string,csv:?string} `path` for a file on
     *         disk; `csv` for the text of an export made before files were kept there.
     */
    public function download(SubmissionAccessScope $scope, int $runId): array
    {
        $run = $this->accessible($scope, $runId);
        $state = $this->stateOf($run);
        if ($state === SubmissionExportRun::REMOVED_DELETED) {
            throw new DomainException('The submission export is unavailable: it was deleted.');
        }
        if ($state === SubmissionExportRun::REMOVED_EXPIRED) {
            throw new DomainException('The submission export is unavailable: it expired.');
        }

        $file = $this->exports->file($runId);
        if ($file !== null) {
            return [
                'name' => sprintf('%s-%s.%s', $file['subject'], $run->createdAt->format('Y-m-d'), $file['extension']),
                'content_type' => $file['content_type'],
                'path' => $file['path'],
                'csv' => null,
            ];
        }

        $csv = $this->exports->artifact($runId);
        if ($csv === null) {
            throw new DomainException('The submission export artifact is not ready.');
        }

        return [
            'name' => 'corex-submissions-' . $runId . '.csv',
            'content_type' => 'text/csv; charset=utf-8',
            'path' => null,
            'csv' => $csv,
        ];
    }

    /**
     * Removes an export's file and keeps its entry, which then says who deleted it.
     */
    public function delete(SubmissionAccessScope $scope, int $runId): void
    {
        $run = $this->accessible($scope, $runId);
        if ($this->stateOf($run) !== self::STATE_READY) {
            throw new DomainException('The submission export holds no file to delete.');
        }

        $this->exports->removeFile($runId, SubmissionExportRun::REMOVED_DELETED, $scope->actorId);
        $this->audit($run, $scope->actorId);
    }

    private function accessible(SubmissionAccessScope $scope, int $runId): SubmissionExportRun
    {
        $run = $this->exports->find($runId);
        if ($run === null || (! $scope->manageAll && $run->actorId !== $scope->actorId)) {
            throw new DomainException('The submission export is unavailable.');
        }

        return $run;
    }

    /**
     * What became of a run's file: deleted, expired, there to download, or not written.
     *
     * A file past its retention is expired from that moment, whether or not the sweep has been by
     * to remove it.
     */
    private function stateOf(SubmissionExportRun $run): string
    {
        if ($run->removedReason !== '') {
            return $run->removedReason;
        }
        if ($this->exports->file($run->id) === null && $this->exports->artifact($run->id) === null) {
            return self::STATE_PENDING;
        }

        return $this->retention->hasExpired($run) ? SubmissionExportRun::REMOVED_EXPIRED : self::STATE_READY;
    }

    private function nameOf(int $userId): string
    {
        return $userId > 0 ? $this->owners->nameOf('user', (string) $userId) : '';
    }

    private function audit(SubmissionExportRun $run, int $actorId): void
    {
        $now = new DateTimeImmutable('now');
        $this->activity->record(
            actorId: $actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: 'User #' . $actorId,
            area: ActivityEvent::AREA_SUBMISSIONS,
            kind: 'submission.export.deleted',
            targetType: 'submission_export',
            targetId: (string) $run->id,
            targetLabel: 'Submission export #' . $run->id,
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => 'submission.export.deleted', 'args' => ['count' => $run->recordCount]],
            context: ['format' => $run->format, 'record_count' => $run->recordCount, 'made_by' => $run->actorId],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: $now->add(new DateInterval('P1Y')),
            occurredAt: $now,
        );
    }
}
