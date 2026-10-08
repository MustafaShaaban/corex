<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataQueryService;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\ExportableDataSource;
use Corex\Config\Export\ExportWriters;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use DomainException;
use InvalidArgumentException;

/** Validates source-scoped exports and queues durable private history records. */
final readonly class DataExportService
{
    public function __construct(
        private DataSourceService $sources,
        private DataQueryService $queries,
        private DataExportStore $exports,
        private DataExportJobQueue $jobs,
    ) {
    }

    public function request(DataExportRequest $request): DataExportRun
    {
        $source = $this->sources->authorize($request->actorId, $request->sourceKey, self::operationFor($request->format));
        if (! $source instanceof ExportableDataSource) {
            throw new DomainException('The data source does not provide export rows and a field schema.');
        }
        $personal = $this->validateColumns($request, $source->fields());
        $count = $this->count($request);
        if ($count === 0) {
            throw new DomainException('There is nothing to export.');
        }
        if ($request->format === ExportWriters::PDF && $count > ExportWriters::PDF_MOST_RECORDS) {
            throw new DomainException(sprintf(
                'A PDF holds up to %d records. Export fewer, or choose a workbook.',
                ExportWriters::PDF_MOST_RECORDS,
            ));
        }
        $run = $this->exports->create(DataExportRun::queued($request, $count, $personal));

        return $this->exports->attachJob($run->id, $this->jobs->enqueue($run));
    }

    /**
     * How many records each scope would export, before anything is exported.
     *
     * @param list<int>           $selectedIds
     * @param array<string,mixed> $query       The filters in force.
     *
     * @return array{selected:int,filtered:int,all:int}
     */
    public function preview(int $actorId, string $sourceKey, array $selectedIds, array $query): array
    {
        $this->sources->authorize($actorId, $sourceKey, DataSourceCapabilities::EXPORT_CSV);
        $selected = 0;
        foreach ($selectedIds as $id) {
            $selected += $this->queries->detail($actorId, $sourceKey, (int) $id) === null ? 0 : 1;
        }

        return [
            DataExportRequest::SCOPE_SELECTED => $selected,
            DataExportRequest::SCOPE_FILTERED => $this->total($actorId, $sourceKey, $query),
            DataExportRequest::SCOPE_ALL => $this->total($actorId, $sourceKey, []),
        ];
    }

    /**
     * Takes one step of an export now, for the person waiting on it.
     *
     * @return array{state:string,processed:int,total:int,error:string}
     */
    public function advance(int $actorId, int $runId, bool $manageAll, string $sourceKey): array
    {
        $run = $this->exports->find($runId);
        if ($run === null || (! $manageAll && $run->actorId !== $actorId) || $run->sourceKey !== $sourceKey) {
            throw new DomainException('The data export is unavailable.');
        }

        return $this->jobs->advance($run->jobId);
    }

    /** @return list<DataExportRun> */
    public function history(int $actorId, bool $manageAll = false, int $limit = 50): array
    {
        return $this->exports->history($actorId, $manageAll, min(100, max(1, $limit)));
    }

    /**
     * A finished export, for the person who made it or one who may manage every export.
     *
     * @return array{filename:string,mime:string,content:string} Named for what it holds and the
     *         day it was made; the caller adds the site.
     */
    public function download(int $actorId, int $runId, bool $manageAll, string $sourceKey = ''): array
    {
        $run = $this->exports->find($runId);
        if ($run === null || $run->state !== DataExportRun::STATE_COMPLETED
            || (! $manageAll && $run->actorId !== $actorId)
            || ($sourceKey !== '' && $run->sourceKey !== $sourceKey)) {
            throw new DomainException('The data export artifact is unavailable.');
        }

        $file = $this->exports->file($runId);
        $name = sprintf('%s-%s', $run->sourceKey, $run->createdAt->format('Y-m-d'));
        if ($file !== null) {
            return [
                'filename' => $name . '.' . $file['extension'],
                'mime' => $file['content_type'],
                // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a local file CoreX wrote, handed back as the answer.
                'content' => (string) file_get_contents($file['path']),
            ];
        }

        // An export made before files were kept on disk has only what was stored with it.
        $artifact = $this->exports->artifact($runId);
        if ($artifact === null) {
            throw new DomainException('The data export artifact is unavailable.');
        }

        return [
            'filename' => $name . '.' . $run->format,
            'mime' => $run->format === ExportWriters::XLSX
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv; charset=utf-8',
            'content' => $artifact,
        ];
    }

    /** The source operation a format is allowed under. */
    public static function operationFor(string $format): string
    {
        return $format === ExportWriters::XLSX
            ? DataSourceCapabilities::EXPORT_XLSX
            : DataSourceCapabilities::EXPORT_CSV;
    }

    /** @param list<DataField> $fields @return list<string> */
    private function validateColumns(DataExportRequest $request, array $fields): array
    {
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[$field->key] = $field;
        }
        $personal = [];
        foreach ($request->columns as $column) {
            if (! isset($fieldMap[$column])) {
                throw new InvalidArgumentException('The data export contains an undeclared column.');
            }
            if ($fieldMap[$column]->personalDataClass !== DataField::PERSONAL_NONE) {
                $personal[] = $fieldMap[$column]->personalDataClass;
            }
        }
        $personal = array_values(array_unique($personal));
        sort($personal);
        if ($personal !== [] && ! $request->personalDataAcknowledged) {
            throw new DomainException('The actor must acknowledge the personal data export warning.');
        }

        return $personal;
    }

    private function count(DataExportRequest $request): int
    {
        if ($request->scope === DataExportRequest::SCOPE_SELECTED) {
            foreach ($request->selectedIds as $id) {
                if ($this->queries->detail($request->actorId, $request->sourceKey, $id) === null) {
                    throw new DomainException('One or more selected export records are unavailable.');
                }
            }

            return count($request->selectedIds);
        }

        return $this->total(
            $request->actorId,
            $request->sourceKey,
            $request->scope === DataExportRequest::SCOPE_FILTERED ? $request->query : [],
        );
    }

    /** @param array<string,mixed> $query */
    private function total(int $actorId, string $sourceKey, array $query): int
    {
        return (int) $this->queries->query($actorId, $sourceKey, DataQuery::from($query))['total'];
    }
}
