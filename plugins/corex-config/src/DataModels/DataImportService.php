<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\WritableDataSource;
use Corex\Data\DataSourceCapabilities;
use DateTimeImmutable;
use DomainException;

/** Immutable dry-run planning and checksum-confirmed bounded commit orchestration. */
final class DataImportService
{
    public function __construct(
        private readonly DataSourceService $sources,
        private readonly DataImportStore $imports,
        private readonly DataImportJobQueue $jobs,
        private readonly ActivityService $activity,
    ) {
    }

    public function dryRun(DataImportRequest $request): DataImportRun
    {
        $source = $this->sources->authorize(
            $request->actorId,
            $request->sourceKey,
            DataSourceCapabilities::IMPORT_DRY_RUN,
        );
        if (! $source instanceof FieldAwareDataSource || ! $source instanceof WritableDataSource) {
            throw new DomainException('The data source does not provide an import write adapter and field schema.');
        }

        $run = $this->imports->create(DataImportRun::planned(
            $request,
            DataImportPlanner::plan($request, $source->fields()),
        ));
        $this->audit($run, 'data.import.validated', ActivityEvent::OUTCOME_SUCCESS);

        return $run;
    }

    public function commit(int $actorId, int $runId, string $inputHash): DataImportRun
    {
        $run = $this->imports->find($runId);
        if ($run === null || $run->actorId !== $actorId || $run->state !== DataImportRun::STATE_VALID) {
            throw new DomainException('The data import cannot be committed.');
        }
        if (! hash_equals($run->inputHash, $inputHash)) {
            throw new DomainException('The data import checksum does not match the dry run.');
        }
        $this->sources->authorize($actorId, $run->sourceKey, DataSourceCapabilities::IMPORT_COMMIT);
        $queued = $this->imports->attachJob($run->id, $this->jobs->enqueue($run));
        $this->audit($queued, 'data.import.queued', ActivityEvent::OUTCOME_QUEUED);

        return $queued;
    }

    /** @param array<string,string> $mapping */
    public function remap(int $actorId, int $runId, array $mapping, string $unknownPolicy): DataImportRun
    {
        $run = $this->imports->find($runId);
        if ($run === null || $run->actorId !== $actorId || $run->state === DataImportRun::STATE_COMMITTING) {
            throw new DomainException('The data import is unavailable for remapping.');
        }

        return $this->dryRun(DataImportRequest::from([
            'actor_id' => $actorId,
            'source_key' => $run->sourceKey,
            'header' => $run->header,
            'rows' => $run->sourceRows,
            'mapping' => $mapping,
            'unknown_policy' => $unknownPolicy,
            'file_name' => $run->fileName,
        ]));
    }

    private function audit(DataImportRun $run, string $kind, string $outcome): void
    {
        $now = new DateTimeImmutable('now');
        $this->activity->record(
            actorId: $run->actorId,
            actorKind: ActivityEvent::ACTOR_USER,
            actorLabel: 'User #' . $run->actorId,
            area: ActivityEvent::AREA_DATA_MODELS,
            kind: $kind,
            targetType: 'data_import',
            targetId: (string) $run->id,
            targetLabel: 'Data import #' . $run->id,
            outcome: $outcome,
            summary: ['key' => $kind, 'args' => ['source' => $run->sourceKey]],
            context: [
                'source' => $run->sourceKey,
                'accepted_count' => count($run->acceptedRows),
                'rejected_count' => count($run->rejectedRows),
                'unknown_policy' => $run->unknownPolicy,
            ],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: $now->modify('+1 year'),
            occurredAt: $now,
        );
    }
}
