<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;

/**
 * The registered data sources behind the Corex → Data screen, keyed by source key. The
 * framework registers the submissions source; add-ons register their custom-table sources
 * (spec 030).
 */
final class DataRegistry
{
    /** @var array<string,DataSource> */
    private array $sources = [];

    public function register(DataSource $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    /**
     * @return list<DataSource>
     */
    public function all(): array
    {
        return array_values($this->sources);
    }

    public function find(string $key): ?DataSource
    {
        return $this->sources[$key] ?? null;
    }

    public function capabilities(string $key): ?DataSourceCapabilities
    {
        $source = $this->find($key);

        if ($source === null) {
            return null;
        }

        if ($source instanceof CapabilityAwareDataSource) {
            return $source->capabilities();
        }

        return new DataSourceCapabilities(
            sourceKey: $source->key(),
            read: true,
            query: $source instanceof QueryableDataSource,
            schema: $source instanceof SchemaAwareDataSource,
            detail: $source instanceof QueryableDataSource,
            create: false,
            update: false,
            delete: false,
            bulkUpdate: false,
            bulkDelete: false,
            importDryRun: false,
            importCommit: false,
            exportCsv: false,
            exportXlsx: false,
            migrations: false,
            rollback: false,
            maxPageSize: 100,
            permissionMap: ['read' => CorexAbility::MANAGE_DATA],
        );
    }

    /** @return list<DataField> */
    public function fields(string $key): array
    {
        $source = $this->find($key);

        if ($source === null) {
            return [];
        }

        if ($source instanceof FieldAwareDataSource) {
            return $source->fields();
        }

        return array_map(
            static fn (array $column): DataField => new DataField(
                key: (string) $column['id'],
                label: (string) $column['label'],
                type: DataField::TYPE_TEXT,
                required: false,
                nullable: true,
                readOnly: true,
                filterOperators: ['equals', 'contains'],
                sortable: true,
                personalDataClass: DataField::PERSONAL_NONE,
                validation: [],
                importAliases: [],
            ),
            $source->columns(),
        );
    }

    /** @return list<array{key:string,label:string,capabilities:array<string,mixed>,fields:list<array<string,mixed>>}> */
    public function describe(): array
    {
        return array_map(
            fn (DataSource $source): array => [
                'key'          => $source->key(),
                'label'        => $source->label(),
                'capabilities' => $this->capabilities($source->key())?->toArray() ?? [],
                'fields'       => array_map(
                    static fn (DataField $field): array => $field->toArray(),
                    $this->fields($source->key()),
                ),
            ],
            $this->all(),
        );
    }
}
