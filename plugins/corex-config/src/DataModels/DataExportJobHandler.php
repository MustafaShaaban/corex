<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\ExportableDataSource;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobHandler;
use DateTimeImmutable;
use DomainException;

/**
 * Gathers a Data export a batch at a time, and writes its file when the last batch has arrived.
 *
 * An export holds a part for each model (spec 109, slice 2). A step gathers from one part: the
 * one whose turn it is, read off how many records the job has been through. The file is written
 * once, in the last step, with a sheet for each part.
 */
final readonly class DataExportJobHandler implements JobHandler
{
    public const KIND = 'data.export';

    public function __construct(
        private DataSourceService $sources,
        private DataExportStore $exports,
        private DataExportFiles $files,
        private ActivityService $activity,
    ) {
    }

    public function kind(): string { return self::KIND; }

    public function handle(BoundedJob $job, int $batchSize): BoundedJob
    {
        $run = $this->exports->findByHash($job->inputHash);
        if ($run === null || $run->state !== DataExportRun::STATE_QUEUED) {
            throw new DomainException('The queued data export is unavailable.');
        }
        [$index, $offset] = self::turn($run, $job->processed);
        $part = $run->parts[$index];
        $source = $this->sourceOf($job->actorId, $run, $part);

        if ($source === null) {
            // The rest of its records are not coming; the job goes on past them.
            $run = $this->exports->leaveOut($run->id, $part->sourceKey);
            $gathered = $part->recordCount - $offset;
        } else {
            $rows = $this->rows($source, $run, $part, $offset, max(1, min(100, $batchSize)));
            $this->files->collect($run, $index, $rows);
            $gathered = count($rows);
        }

        $processed = $job->processed + $gathered;
        $advanced = $job->advance((string) $processed, $processed, $processed, 0, null, new DateTimeImmutable('now'));

        return $processed < $job->total ? $advanced : $this->finish($run, $advanced);
    }

    /**
     * Whose turn it is after this many records, and how far into its own records that is.
     *
     * A part that was left out still takes its place in the count: the job's total was fixed
     * when the export was asked for.
     *
     * @return array{0:int,1:int} The part's index, and the offset within it.
     */
    private static function turn(DataExportRun $run, int $processed): array
    {
        $before = 0;
        foreach ($run->parts as $index => $part) {
            if ($processed < $before + $part->recordCount) {
                return [$index, $processed - $before];
            }
            $before += $part->recordCount;
        }

        throw new DomainException('The data export has no records left to gather.');
    }

    /**
     * The source of a part, or null when it can no longer be exported and the run has others.
     *
     * A model can go while an export of several is written: its add-on switched off, the right
     * to export it taken back. The others are still written, and the run says which was left
     * out. An export of one model has nothing else to write, and fails as it always did.
     */
    private function sourceOf(int $actorId, DataExportRun $run, DataExportPart $part): ?ExportableDataSource
    {
        try {
            $source = $this->sources->authorize($actorId, $part->sourceKey, DataExportService::operationFor($run->format));
        } catch (DomainException $refusal) {
            if (! $run->isSeveral()) {
                throw $refusal;
            }

            return null;
        }
        if (! $source instanceof ExportableDataSource) {
            throw new DomainException('The data source export adapter is unavailable.');
        }

        return $source;
    }

    /**
     * The next batch of a part, as the rows the source hands an export. However the records were
     * chosen, they are read in that one shape: which shape a cell is read from is the source's
     * to know.
     *
     * @return list<array<string,mixed>>
     */
    private function rows(ExportableDataSource $source, DataExportRun $run, DataExportPart $part, int $offset, int $limit): array
    {
        if ($run->scope === DataExportRequest::SCOPE_SELECTED) {
            $rows = $source->exportRowsOf(array_slice($run->selectedIds, $offset, $limit));
        } else {
            $input = $run->scope === DataExportRequest::SCOPE_FILTERED ? $run->query : [];
            $input['page'] = intdiv($offset, $limit) + 1;
            $input['per_page'] = $limit;
            $rows = $source->exportRows(DataQuery::from($input));
        }
        // No more than was counted when the export was asked for. A record that arrives in the
        // source while it is written makes the last batch larger than what is left, and the job
        // refuses to count past its total.
        $rows = array_slice($rows, 0, max(0, $part->recordCount - $offset));
        if ($rows === []) {
            throw new DomainException('The data export scope changed before completion.');
        }

        return $rows;
    }

    private function finish(DataExportRun $run, BoundedJob $job): BoundedJob
    {
        $models = [];
        foreach ($run->parts as $index => $part) {
            // Asked again: what a sheet is named and headed by is the source's to say, and a
            // source gathered three steps ago may have gone since.
            $source = $part->leftOut ? null : $this->sourceOf($job->actorId, $run, $part);
            if ($source === null) {
                $run = $part->leftOut ? $run : $this->exports->leaveOut($run->id, $part->sourceKey);
                continue;
            }
            $models[] = ['index' => $index, 'label' => $source->label() ?: $part->sourceKey, 'fields' => $source->fields()];
        }
        if ($models === []) {
            throw new DomainException('No model of the data export can be read any more.');
        }

        $file = $this->files->finish($run, $models);
        $this->exports->saveFile($run->id, [
            'path' => $file->path,
            'extension' => $file->extension,
            'content_type' => $file->contentType,
        ]);
        $this->exports->finish($run->id, $run->recordCount);
        $this->audit($run);

        return $job->complete('data-export:' . $run->id, new DateTimeImmutable('now'));
    }

    private function audit(DataExportRun $run): void
    {
        $now = new DateTimeImmutable('now');
        $written = array_values(array_filter($run->parts, static fn (DataExportPart $part): bool => ! $part->leftOut));
        $this->activity->record(
            actorId: $run->actorId,
            actorKind: ActivityEvent::ACTOR_SYSTEM,
            actorLabel: 'CoreX Jobs',
            area: ActivityEvent::AREA_DATA_MODELS,
            kind: 'data.export.completed',
            targetType: 'data_export',
            targetId: (string) $run->id,
            targetLabel: 'Data export #' . $run->id,
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => 'data.export.completed', 'args' => ['source' => $written[0]->sourceKey]],
            context: [
                'source' => $written[0]->sourceKey,
                'sources' => array_map(static fn (DataExportPart $part): string => $part->sourceKey, $written),
                'scope' => $run->scope, 'format' => $run->format, 'row_count' => $run->recordCount,
                // Which columns, never what they held.
                'columns' => $run->isSeveral()
                    ? array_column(array_map(static fn (DataExportPart $part): array => $part->toArray(), $written), 'columns', 'source_key')
                    : $run->columns,
            ],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: $now->modify('+1 year'),
            occurredAt: $now,
        );
    }
}
