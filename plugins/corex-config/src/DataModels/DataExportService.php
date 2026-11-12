<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Config\Data\DataAccessRestricted;
use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataQueryService;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\ExportableDataSource;
use Corex\Config\Data\PersonalDataNotAllowed;
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
        $source = $this->exportable($request->actorId, $request->sourceKey, $request->format);
        $personal = $this->personalClasses($request->columns, $source->fields());
        $this->assertMayTakeOut($request->actorId, $personal, $request->personalDataAcknowledged);
        $count = $this->count($request);
        if ($count === 0) {
            throw new DomainException('There is nothing to export.');
        }
        $this->assertFits($request->format, $count);

        return $this->queue(DataExportRun::queued($request, $count, $personal));
    }

    /**
     * One export of several models, every record of each (spec 109, slice 2).
     *
     * A model that holds no records, or of which this person may export no column, is left out
     * of the run; an export in which that is every model makes no file. A model that belongs to a
     * screen this person may not open is kept in the run as left out, with why, and never read
     * (spec 112, FR-030); an export of nothing but such models is refused, naming them.
     */
    public function requestSeveral(DataExportSetRequest $request): DataExportRun
    {
        $parts    = [];
        $readable = 0;
        foreach ($request->parts as $asked) {
            $part = $this->sources->withheldFrom($request->actorId, $asked['source']) === null
                ? $this->partOf($request, $asked['source'], $asked['columns'])
                : $this->withheld($request->actorId, $asked['source']);
            if ($part !== null) {
                $parts[]   = $part;
                $readable += $part->leftOut ? 0 : 1;
            }
        }
        if ($readable === 0) {
            throw $parts === []
                ? new DomainException('There is nothing to export.')
                : DataAccessRestricted::forEveryModelOf(array_map(static fn (DataExportPart $part): string => $part->label, $parts));
        }
        $run = DataExportRun::queuedSeveral($request, $parts);
        $this->assertMayTakeOut($request->actorId, $run->personalDataClasses, $request->personalDataAcknowledged);
        $this->assertFits($request->format, $run->recordCount);

        return $this->queue($run);
    }

    /**
     * How many records each model would give, before anything is exported.
     *
     * @param list<string> $sourceKeys
     *
     * @return array<string,int> By source key, in the order asked for.
     */
    public function previewSeveral(int $actorId, array $sourceKeys): array
    {
        $counts = [];
        foreach ($sourceKeys as $sourceKey) {
            $this->sources->authorize($actorId, $sourceKey, DataSourceCapabilities::EXPORT_CSV);
            $counts[$sourceKey] = $this->total($actorId, $sourceKey, []);
        }

        return $counts;
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
        if ($run === null || (! $manageAll && $run->actorId !== $actorId) || ($sourceKey !== '' && ! $run->holds($sourceKey))) {
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
            || ($sourceKey !== '' && ! $run->holds($sourceKey))) {
            throw new DomainException('The data export artifact is unavailable.');
        }
        // An export made before a model was restricted, or before this person lost the ability
        // its records need, is not handed out again (spec 112).
        foreach ($run->parts as $part) {
            if (! $part->leftOut && $this->sources->withheldFrom($actorId, $part->sourceKey) !== null) {
                $this->sources->noteWithheld($actorId, $part->sourceKey);

                throw DataAccessRestricted::forModel();
            }
        }

        $file = $this->exports->file($runId);
        // Named for what it holds: the model, or how many models.
        $written = count(array_filter($run->parts, static fn (DataExportPart $part): bool => ! $part->leftOut));
        $subject = $run->isSeveral() ? sprintf('%d-models', $written) : $run->sourceKey;
        $name = sprintf('%s-%s', $subject, $run->createdAt->format('Y-m-d'));
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

    private function exportable(int $actorId, string $sourceKey, string $format): ExportableDataSource
    {
        $source = $this->sources->authorize($actorId, $sourceKey, self::operationFor($format));
        if (! $source instanceof ExportableDataSource) {
            throw new DomainException('The data source does not provide export rows and a field schema.');
        }

        return $source;
    }

    /** A model this person may not read, kept in the run as left out; the refusal is recorded. */
    private function withheld(int $actorId, string $sourceKey): DataExportPart
    {
        $this->sources->noteWithheld($actorId, $sourceKey);

        return DataExportPart::restricted($sourceKey, $this->sources->label($sourceKey));
    }

    /**
     * One model of an export of several, or null when it would give nothing.
     *
     * @param list<string>|null $columns The columns asked for; null for every column this person
     *                                   may export.
     */
    private function partOf(DataExportSetRequest $request, string $sourceKey, ?array $columns): ?DataExportPart
    {
        $fields = $this->exportable($request->actorId, $sourceKey, $request->format)->fields();
        $columns ??= $this->offered($request->actorId, $fields);
        $count = $this->total($request->actorId, $sourceKey, []);
        if ($columns === [] || $count === 0) {
            return null;
        }

        return new DataExportPart($sourceKey, $columns, $count, $this->personalClasses($columns, $fields));
    }

    /**
     * Every column of a model this person may export: all of them, or the ones that hold nothing
     * personal for a person not allowed to take personal data out.
     *
     * @param list<DataField> $fields
     *
     * @return list<string>
     */
    private function offered(int $actorId, array $fields): array
    {
        $mayTakePersonal = $this->sources->mayExportPersonalData($actorId);

        return array_values(array_map(
            static fn (DataField $field): string => $field->key,
            array_filter($fields, static fn (DataField $field): bool => $mayTakePersonal || ! $field->holdsPersonalData()),
        ));
    }

    /**
     * The kinds of personal data among these columns.
     *
     * @param list<string>    $columns
     * @param list<DataField> $fields
     *
     * @return list<string>
     */
    private function personalClasses(array $columns, array $fields): array
    {
        $fieldMap = [];
        foreach ($fields as $field) {
            $fieldMap[$field->key] = $field;
        }
        $personal = [];
        foreach ($columns as $column) {
            if (! isset($fieldMap[$column])) {
                throw new InvalidArgumentException('The data export contains an undeclared column.');
            }
            if ($fieldMap[$column]->holdsPersonalData()) {
                $personal[] = $fieldMap[$column]->personalDataClass;
            }
        }
        $personal = array_values(array_unique($personal));
        sort($personal);

        return $personal;
    }

    /** @param list<string> $personal The kinds of personal data an export would hold. */
    private function assertMayTakeOut(int $actorId, array $personal, bool $acknowledged): void
    {
        if ($personal === []) {
            return;
        }
        // Asked before the acknowledgement: ticking a box is not what allows it.
        if (! $this->sources->mayExportPersonalData($actorId)) {
            throw PersonalDataNotAllowed::inAnExport();
        }
        if (! $acknowledged) {
            throw new DomainException('The actor must acknowledge the personal data export warning.');
        }
    }

    private function assertFits(string $format, int $count): void
    {
        if ($format === ExportWriters::PDF && $count > ExportWriters::PDF_MOST_RECORDS) {
            throw new DomainException(sprintf(
                'A PDF holds up to %d records. Export fewer, or choose a workbook.',
                ExportWriters::PDF_MOST_RECORDS,
            ));
        }
    }

    private function queue(DataExportRun $run): DataExportRun
    {
        $run = $this->exports->create($run);

        return $this->exports->attachJob($run->id, $this->jobs->enqueue($run));
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
