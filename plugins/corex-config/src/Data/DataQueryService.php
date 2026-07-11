<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;
use DomainException;
use InvalidArgumentException;

/**
 * Validates source-defined filters and sorting before executing a real adapter query.
 */
final readonly class DataQueryService
{
    public function __construct(private DataRegistry $registry, private DataSourceService $sources)
    {
    }

    /** @return array<string,mixed> */
    public function query(int $actorId, string $sourceKey, DataQuery $query): array
    {
        $source = $this->sources->authorize($actorId, $sourceKey, DataSourceCapabilities::QUERY);
        if (! $source instanceof QueryableDataSource) {
            throw new DomainException('The data source does not provide a query adapter.');
        }
        $this->validate($sourceKey, $query);
        $capabilities = $this->registry->capabilities($sourceKey);
        $query = $query->withPerPage(min($query->perPage, $capabilities?->maxPageSize ?? DataQuery::MAX_PER_PAGE));

        return [
            'columns' => $source->columns(),
            'fields' => array_map(static fn (DataField $field): array => $field->toArray(), $this->registry->fields($sourceKey)),
            'rows' => $source->query($query),
            'total' => $source->count($query),
            'page' => $query->page,
            'per_page' => $query->perPage,
        ];
    }

    /** @return array<string,mixed>|null */
    public function detail(int $actorId, string $sourceKey, int $recordId): ?array
    {
        $source = $this->sources->authorize($actorId, $sourceKey, DataSourceCapabilities::DETAIL);
        if (! $source instanceof QueryableDataSource) {
            throw new DomainException('The data source does not provide a detail adapter.');
        }

        return $source->record($recordId);
    }

    private function validate(string $sourceKey, DataQuery $query): void
    {
        $fields = [];
        foreach ($this->registry->fields($sourceKey) as $field) {
            $fields[$field->key] = $field;
        }
        foreach ($query->filters as $key => $value) {
            if ($value !== '' && (! isset($fields[$key]) || ! in_array('equals', $fields[$key]->filterOperators, true))) {
                throw new InvalidArgumentException('The data query filter is not declared by the source.');
            }
        }
        if ($query->sortColumn !== '' && (! isset($fields[$query->sortColumn]) || ! $fields[$query->sortColumn]->sortable)) {
            throw new InvalidArgumentException('The data query sort is not declared by the source.');
        }
    }
}
