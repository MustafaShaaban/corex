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

    /** @var list<callable():list<DataSource>> */
    private array $deferred = [];

    private bool $resolved = false;

    public function register(DataSource $source): void
    {
        $this->sources[$source->key()] = $source;
    }

    /**
     * Register sources that cannot be built yet, to be built the first time the registry is read.
     *
     * Managed tables are the case this exists for. The registry is constructed while corex-config
     * boots — before `init`, and therefore before an add-on has had a chance to declare its own
     * table — so anything built eagerly at construction silently excluded every add-on's model.
     * The newsletter subscribers table, this framework's reference importable model, was invisible
     * in the admin for exactly that reason while being perfectly visible to WP-CLI, which resolves
     * the registry long after boot.
     *
     * @param callable():list<DataSource> $factory
     */
    public function registerDeferred(callable $factory): void
    {
        $this->deferred[] = $factory;
        $this->resolved   = false;
    }

    /**
     * @return list<DataSource>
     */
    public function all(): array
    {
        $this->resolveDeferred();

        return array_values($this->sources);
    }

    public function find(string $key): ?DataSource
    {
        $this->resolveDeferred();

        return $this->sources[$key] ?? null;
    }

    private function resolveDeferred(): void
    {
        if ($this->resolved || $this->deferred === []) {
            $this->resolved = true;

            return;
        }

        // Set before running the factories: a factory that reads the registry back would otherwise
        // recurse forever.
        $this->resolved = true;

        foreach ($this->deferred as $factory) {
            foreach ($factory() as $source) {
                $this->register($source);
            }
        }
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
