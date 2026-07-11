<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Database\Schema\ManagedTable;
use Corex\Access\CorexAbility;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;

/**
 * A DataSource over a Corex-managed custom table (spec 038): it exposes the table's declared
 * columns and shapes each row to `id` + exactly those columns (extra columns dropped, missing
 * ones defaulted to ''), so a managed table appears in Corex → Data like any other source with
 * no new UI. The `$wpdb` access lives in the injected reader, so this shaping is unit-tested.
 */
final class TableDataSource implements QueryableDataSource, SchemaAwareDataSource, CapabilityAwareDataSource, FieldAwareDataSource
{
    public function __construct(
        private readonly ManagedTable $table,
        private readonly TableDataReader $reader,
    ) {
    }

    public function key(): string
    {
        return 'table-' . str_replace('_', '-', $this->table->name);
    }

    public function label(): string
    {
        return $this->table->displayLabel();
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    public function columns(): array
    {
        return $this->table->displayColumns();
    }

    /**
     * @return list<array<string,scalar>>
     */
    public function rows(int $page, int $perPage): array
    {
        $columns = $this->table->columnIds();

        return array_map(
            function (array $record) use ($columns): array {
                $row = ['id' => $record['id'] ?? 0];

                foreach ($columns as $column) {
                    $row[$column] = $record[$column] ?? '';
                }

                return $row;
            },
            $this->reader->page($this->table->name, $columns, max(1, $page), max(1, $perPage)),
        );
    }

    public function total(): int
    {
        return $this->reader->total($this->table->name);
    }

    public function delete(int $id): bool
    {
        return $this->reader->delete($this->table->name, $id);
    }

    public function query(DataQuery $query): array
    {
        return $this->shapeRows($this->reader->query($this->table->name, $this->table->columnIds(), $query));
    }

    public function count(DataQuery $query): int
    {
        return $this->reader->countQuery($this->table->name, $this->table->columnIds(), $query);
    }

    public function record(int $id): ?array
    {
        $record = $this->reader->find($this->table->name, $this->table->columnIds(), $id);

        return $record === null ? null : $this->shapeRow($record);
    }

    public function schema(): array
    {
        return array_map(
            static fn (array $column): array => ['name' => $column['label'], 'type' => DataField::TYPE_TEXT],
            $this->table->displayColumns(),
        );
    }

    public function capabilities(): DataSourceCapabilities
    {
        return new DataSourceCapabilities(
            sourceKey: $this->key(),
            read: true,
            query: true,
            schema: true,
            detail: true,
            create: false,
            update: false,
            delete: true,
            bulkUpdate: false,
            bulkDelete: false,
            importDryRun: false,
            importCommit: false,
            exportCsv: true,
            exportXlsx: false,
            migrations: false,
            rollback: false,
            maxPageSize: 100,
            permissionMap: [
                DataSourceCapabilities::READ       => CorexAbility::MANAGE_DATA,
                DataSourceCapabilities::QUERY      => CorexAbility::MANAGE_DATA,
                DataSourceCapabilities::DETAIL     => CorexAbility::MANAGE_DATA,
                DataSourceCapabilities::DELETE     => CorexAbility::MANAGE_DATA,
                DataSourceCapabilities::EXPORT_CSV => CorexAbility::MANAGE_DATA,
            ],
        );
    }

    public function fields(): array
    {
        return array_map(
            static fn (array $column): DataField => new DataField(
                key: $column['id'],
                label: $column['label'],
                type: DataField::TYPE_TEXT,
                required: false,
                nullable: true,
                readOnly: true,
                filterOperators: ['equals'],
                sortable: true,
                personalDataClass: DataField::PERSONAL_NONE,
                validation: [],
                importAliases: [],
            ),
            $this->table->displayColumns(),
        );
    }

    /** @param list<array<string,scalar>> $records @return list<array<string,scalar>> */
    private function shapeRows(array $records): array
    {
        return array_map($this->shapeRow(...), $records);
    }

    /** @param array<string,scalar> $record @return array<string,scalar> */
    private function shapeRow(array $record): array
    {
        $row = ['id' => $record['id'] ?? 0];
        foreach ($this->table->columnIds() as $column) {
            $row[$column] = $record[$column] ?? '';
        }

        return $row;
    }
}
