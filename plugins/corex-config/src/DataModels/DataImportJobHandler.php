<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\WritableDataSource;
use Corex\Data\DataSourceCapabilities;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobHandler;
use DateTimeImmutable;
use DomainException;
use Throwable;

/** Writes only the accepted immutable dry-run rows in bounded batches. */
final readonly class DataImportJobHandler implements JobHandler
{
    public const KIND = 'data.import';

    public function __construct(
        private DataSourceService $sources,
        private DataImportStore $imports,
        private ActivityService $activity,
    ) {
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function handle(BoundedJob $job, int $batchSize): BoundedJob
    {
        $run = $this->imports->findByHash($job->inputHash);
        if ($run === null || $run->state !== DataImportRun::STATE_COMMITTING) {
            throw new DomainException('The queued data import is unavailable.');
        }
        $source = $this->sources->authorize($job->actorId, $run->sourceKey, DataSourceCapabilities::IMPORT_COMMIT);
        if (! $source instanceof WritableDataSource) {
            throw new DomainException('The data source import adapter is unavailable.');
        }

        $rows = array_slice($run->acceptedRows, $job->processed, max(1, $batchSize));
        [$succeeded, $failed] = $this->write($source->writeAdapter(), $rows);
        $processed = $job->processed + count($rows);
        $advanced = $job->advance(
            (string) $processed,
            $processed,
            $job->succeeded + $succeeded,
            $job->failed + $failed,
            null,
            new DateTimeImmutable('now'),
        );
        if ($processed < $job->total) {
            return $advanced;
        }

        $this->imports->finish($run->id, $advanced->succeeded, $advanced->failed);
        $this->audit($run, $advanced);

        return $advanced->complete('data-import:' . $run->id, new DateTimeImmutable('now'));
    }

    /** @param list<array<string,mixed>> $rows @return array{int,int} */
    private function write(\Corex\Data\DataWriteAdapter $adapter, array $rows): array
    {
        $succeeded = 0;
        $failed = 0;
        foreach ($rows as $values) {
            try {
                $adapter->create($values)->succeeded() ? $succeeded++ : $failed++;
            } catch (Throwable) {
                $failed++;
            }
        }

        return [$succeeded, $failed];
    }

    private function audit(DataImportRun $run, BoundedJob $job): void
    {
        $now = new DateTimeImmutable('now');
        $this->activity->record(
            actorId: $run->actorId,
            actorKind: ActivityEvent::ACTOR_SYSTEM,
            actorLabel: 'CoreX Jobs',
            area: ActivityEvent::AREA_DATA_MODELS,
            kind: 'data.import.completed',
            targetType: 'data_import',
            targetId: (string) $run->id,
            targetLabel: 'Data import #' . $run->id,
            outcome: $job->failed === 0 ? ActivityEvent::OUTCOME_SUCCESS : ActivityEvent::OUTCOME_WARNING,
            summary: ['key' => 'data.import.completed', 'args' => ['source' => $run->sourceKey]],
            context: ['source' => $run->sourceKey, 'committed_count' => $job->succeeded, 'failed_count' => $job->failed],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: $now->modify('+1 year'),
            occurredAt: $now,
        );
    }
}
