<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\QueryableDataSource;
use Corex\Data\DataSourceCapabilities;
use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobHandler;
use DateTimeImmutable;
use DomainException;

/** Resumable source query to private CSV/XLSX artifact handler. */
final readonly class DataExportJobHandler implements JobHandler
{
    public const KIND = 'data.export';

    public function __construct(
        private DataSourceService $sources,
        private DataExportStore $exports,
        private DataExportArtifactWriter $writer,
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
        $operation = $run->format === 'xlsx' ? DataSourceCapabilities::EXPORT_XLSX : DataSourceCapabilities::EXPORT_CSV;
        $source = $this->sources->authorize($job->actorId, $run->sourceKey, $operation);
        if (! $source instanceof QueryableDataSource || ! $source instanceof FieldAwareDataSource) {
            throw new DomainException('The data source export adapter is unavailable.');
        }
        $limit = max(1, min(100, $batchSize));
        $rows = $this->rows($source, $run, $job->processed, $limit);
        if ($rows === [] && $job->processed < $job->total) {
            throw new DomainException('The data export scope changed before completion.');
        }
        $processed = $job->processed + count($rows);
        $final = $processed === $job->total;
        $fields = $this->fields($source, $run->columns);
        $artifact = $this->writer->append(
            DataExportArtifact::start($run->format, $this->exports->artifact($run->id) ?? ''),
            $fields,
            $rows,
            $final,
        );
        $this->exports->saveArtifact($run->id, $artifact->content);
        $advanced = $job->advance((string) $processed, $processed, $processed, 0, null, new DateTimeImmutable('now'));
        if (! $final) {
            return $advanced;
        }

        $this->exports->finish($run->id, $processed);
        $this->audit($run, $processed);

        return $advanced->complete('data-export:' . $run->id, new DateTimeImmutable('now'));
    }

    /** @return list<array<string,mixed>> */
    private function rows(QueryableDataSource $source, DataExportRun $run, int $offset, int $limit): array
    {
        if ($run->scope === DataExportRequest::SCOPE_SELECTED) {
            return array_values(array_filter(array_map(
                static fn (int $id): ?array => $source->record($id),
                array_slice($run->selectedIds, $offset, $limit),
            )));
        }
        $input = $run->scope === DataExportRequest::SCOPE_FILTERED ? $run->query : [];
        $input['page'] = intdiv($offset, $limit) + 1;
        $input['per_page'] = $limit;

        return $source->query(DataQuery::from($input));
    }

    /** @param list<string> $columns @return list<array{key:string,label:string}> */
    private function fields(FieldAwareDataSource $source, array $columns): array
    {
        $labels = [];
        foreach ($source->fields() as $field) {
            $labels[$field->key] = $field->label;
        }

        return array_map(static fn (string $key): array => ['key' => $key, 'label' => $labels[$key]], $columns);
    }

    private function audit(DataExportRun $run, int $rows): void
    {
        $now = new DateTimeImmutable('now');
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
            summary: ['key' => 'data.export.completed', 'args' => ['source' => $run->sourceKey]],
            context: ['source' => $run->sourceKey, 'scope' => $run->scope, 'format' => $run->format, 'row_count' => $rows, 'columns' => $run->columns],
            sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
            retentionUntil: $now->modify('+1 year'),
            occurredAt: $now,
        );
    }
}
