<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use Corex\Config\Data\DataQuery;
use Corex\Config\Data\DataQueryService;
use Corex\Config\Data\DataSourceService;
use Corex\Config\Data\FieldAwareDataSource;
use Corex\Config\Data\QueryableDataSource;
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
        $operation = $request->format === 'xlsx'
            ? DataSourceCapabilities::EXPORT_XLSX
            : DataSourceCapabilities::EXPORT_CSV;
        $source = $this->sources->authorize($request->actorId, $request->sourceKey, $operation);
        if (! $source instanceof QueryableDataSource || ! $source instanceof FieldAwareDataSource) {
            throw new DomainException('The data source does not provide an export query and field schema.');
        }
        $personal = $this->validateColumns($request, $source->fields());
        $count = $this->count($request);
        $run = $this->exports->create(DataExportRun::queued($request, $count, $personal));

        return $this->exports->attachJob($run->id, $this->jobs->enqueue($run));
    }

    /** @return list<DataExportRun> */
    public function history(int $actorId, bool $manageAll = false, int $limit = 50): array
    {
        return $this->exports->history($actorId, $manageAll, min(100, max(1, $limit)));
    }

    /** @return array{filename:string,mime:string,content:string} */
    public function download(int $actorId, int $runId, bool $manageAll, string $sourceKey = ''): array
    {
        $run = $this->exports->find($runId);
        $artifact = $this->exports->artifact($runId);
        if ($run === null || $run->state !== DataExportRun::STATE_COMPLETED
            || (! $manageAll && $run->actorId !== $actorId) || $artifact === null
            || ($sourceKey !== '' && $run->sourceKey !== $sourceKey)) {
            throw new DomainException('The data export artifact is unavailable.');
        }

        return [
            'filename' => sprintf('corex-%s-%d.%s', $run->sourceKey, $run->id, $run->format),
            'mime' => $run->format === 'xlsx'
                ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
                : 'text/csv',
            'content' => $artifact,
        ];
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
        $query = DataQuery::from($request->scope === DataExportRequest::SCOPE_FILTERED ? $request->query : []);

        return (int) $this->queries->query($request->actorId, $request->sourceKey, $query)['total'];
    }
}
