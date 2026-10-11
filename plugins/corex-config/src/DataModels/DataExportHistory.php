<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Closure;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use DateInterval;
use DateTimeImmutable;
use DomainException;

/**
 * What was exported from the Data screen before, and what became of each file (spec 109,
 * FR-012 to FR-014).
 *
 * A person sees their own exports; one who may manage them all sees everybody's. An export
 * that is another person's answers as if it were not there.
 */
final readonly class DataExportHistory
{
    /** The states of an export's file that are not a reason it was removed. */
    public const STATE_READY = 'ready';
    public const STATE_PENDING = 'pending';

    /** @param Closure(int):string $nameOf A person's name by their id; '' when they cannot be named. */
    public function __construct(
        private DataExportStore $exports,
        private DataExportRetention $retention,
        private ActivityService $activity,
        private Closure $nameOf,
    ) {
    }

    /**
     * @return list<array<string,mixed>> Each export as stored, with who made it by name, what
     *         state its file is in and when it expires.
     */
    public function entries(int $actorId, bool $manageAll, int $limit = 50): array
    {
        return array_map(
            fn (DataExportRun $run): array => [
                ...$run->toArray(),
                'actor_name' => $this->named($run->actorId),
                'removed_by_name' => $this->named($run->removedBy),
                'file_state' => $this->stateOf($run),
                'expires_at' => $this->retention->expiryOf($run)->format(DATE_ATOM),
            ],
            $this->exports->history($actorId, $manageAll, min(100, max(1, $limit))),
        );
    }

    /**
     * The run, when this person may download it and its file is there to download.
     */
    public function downloadable(int $actorId, bool $manageAll, int $runId): DataExportRun
    {
        $run = $this->accessible($actorId, $manageAll, $runId);
        $state = $this->stateOf($run);
        if ($state === DataExportRun::REMOVED_DELETED) {
            throw new DomainException(__('This export is unavailable: its file was deleted.', 'corex'));
        }
        if ($state === DataExportRun::REMOVED_EXPIRED) {
            throw new DomainException(__('This export is unavailable: its file expired.', 'corex'));
        }

        return $run;
    }

    /**
     * Removes an export's file and keeps its entry, which then says who deleted it.
     */
    public function delete(int $actorId, bool $manageAll, int $runId): void
    {
        $run = $this->accessible($actorId, $manageAll, $runId);
        if ($this->stateOf($run) !== self::STATE_READY) {
            throw new DomainException(__('This export holds no file to delete.', 'corex'));
        }

        $this->exports->removeFile($runId, DataExportRun::REMOVED_DELETED, $actorId);
        $this->audit($run, $actorId);
    }

    private function accessible(int $actorId, bool $manageAll, int $runId): DataExportRun
    {
        $run = $this->exports->find($runId);
        if ($run === null || (! $manageAll && $run->actorId !== $actorId)) {
            throw new DomainException(__('This export is unavailable.', 'corex'));
        }

        return $run;
    }

    /**
     * What became of a run's file: deleted, expired, there to download, or not written yet.
     *
     * A file past its thirty days is expired from that moment, whether or not the sweep has been
     * by to remove it.
     */
    private function stateOf(DataExportRun $run): string
    {
        if ($run->removedReason !== '') {
            return $run->removedReason;
        }
        if ($this->exports->file($run->id) === null && $this->exports->artifact($run->id) === null) {
            return self::STATE_PENDING;
        }

        return $this->retention->hasExpired($run) ? DataExportRun::REMOVED_EXPIRED : self::STATE_READY;
    }

    private function named(int $userId): string
    {
        return $userId > 0 ? ($this->nameOf)($userId) : '';
    }

    private function audit(DataExportRun $run, int $actorId): void
    {
        $now = new DateTimeImmutable('now');
        $this->activity->record(
            actorId: $actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: 'User #' . $actorId,
            area: ActivityEvent::AREA_DATA_MODELS,
            kind: 'data.export.deleted',
            targetType: 'data_export',
            targetId: (string) $run->id,
            targetLabel: 'Data export #' . $run->id,
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => 'data.export.deleted', 'args' => ['count' => $run->recordCount]],
            context: [
                'sources' => $run->sourceKeys(), 'format' => $run->format,
                'record_count' => $run->recordCount, 'made_by' => $run->actorId,
            ],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: $now->add(new DateInterval('P1Y')),
            occurredAt: $now,
        );
    }
}
