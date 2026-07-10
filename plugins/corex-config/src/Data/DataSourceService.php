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
use Corex\Config\DataModels\MigrationAwareDataSource;
use DomainException;

/**
 * Projects source-declared operations through the current actor's ability map.
 */
final readonly class DataSourceService
{
    private const OPERATIONS = [
        DataSourceCapabilities::READ,
        DataSourceCapabilities::QUERY,
        DataSourceCapabilities::SCHEMA,
        DataSourceCapabilities::DETAIL,
        DataSourceCapabilities::CREATE,
        DataSourceCapabilities::UPDATE,
        DataSourceCapabilities::DELETE,
        DataSourceCapabilities::BULK_UPDATE,
        DataSourceCapabilities::BULK_DELETE,
        DataSourceCapabilities::IMPORT_DRY_RUN,
        DataSourceCapabilities::IMPORT_COMMIT,
        DataSourceCapabilities::EXPORT_CSV,
        DataSourceCapabilities::EXPORT_XLSX,
        DataSourceCapabilities::MIGRATIONS,
        DataSourceCapabilities::ROLLBACK,
    ];

    private const WRITE_OPERATIONS = [
        DataSourceCapabilities::CREATE,
        DataSourceCapabilities::UPDATE,
        DataSourceCapabilities::DELETE,
        DataSourceCapabilities::BULK_UPDATE,
        DataSourceCapabilities::BULK_DELETE,
    ];

    public function __construct(private DataRegistry $registry, private DataAccessPolicy $access)
    {
    }

    /** @return list<array<string,mixed>> */
    public function catalog(int $actorId): array
    {
        return array_map(
            fn (DataSource $source): array => $this->describe($actorId, $source->key()),
            $this->registry->all(),
        );
    }

    /** @return array<string,mixed> */
    public function describe(int $actorId, string $sourceKey): array
    {
        $source = $this->source($sourceKey);
        $capabilities = $this->capabilities($sourceKey);
        $actions = [];
        foreach (self::OPERATIONS as $operation) {
            $actions[$operation] = $this->action($actorId, $source, $capabilities, $operation);
        }
        $readable = $actions[DataSourceCapabilities::READ]['allowed'];

        return [
            'key' => $source->key(),
            'label' => $source->label(),
            'access' => $readable ? 'allowed' : 'denied',
            'capabilities' => $capabilities->toArray(),
            'actions' => $actions,
            'fields' => $readable ? array_map(
                static fn (DataField $field): array => $field->toArray(),
                $this->registry->fields($sourceKey),
            ) : [],
        ];
    }

    public function authorize(int $actorId, string $sourceKey, string $operation): DataSource
    {
        $source = $this->source($sourceKey);
        $action = $this->action($actorId, $source, $this->capabilities($sourceKey), $operation);
        if (! $action['supported']) {
            throw new DomainException('The data source does not support this operation.');
        }
        if (! $action['allowed']) {
            throw new DomainException('The actor does not have permission for this data operation.');
        }

        return $source;
    }

    private function source(string $sourceKey): DataSource
    {
        return $this->registry->find($sourceKey)
            ?? throw new DomainException('The requested data source is unavailable.');
    }

    private function capabilities(string $sourceKey): DataSourceCapabilities
    {
        return $this->registry->capabilities($sourceKey)
            ?? throw new DomainException('The requested data source capabilities are unavailable.');
    }

    /** @return array{supported:bool,allowed:bool,visible:bool,reason:string} */
    private function action(
        int $actorId,
        DataSource $source,
        DataSourceCapabilities $capabilities,
        string $operation,
    ): array
    {
        $declared = $capabilities->supports($operation);
        $missingAdapter = $declared && ! $this->hasAdapter($source, $operation);
        $supported = $declared && ! $missingAdapter;
        $ability = $capabilities->permissionMap[$operation] ?? CorexAbility::MANAGE_DATA;
        $allowed = $supported && $this->access->allows($actorId, $ability);

        return [
            'supported' => $supported,
            'allowed' => $allowed,
            'visible' => $supported && $allowed,
            'reason' => $missingAdapter ? 'no_adapter' : ($supported ? ($allowed ? '' : 'permission') : 'unsupported'),
        ];
    }

    private function hasAdapter(DataSource $source, string $operation): bool
    {
        if (in_array($operation, self::WRITE_OPERATIONS, true)) {
            return $source instanceof WritableDataSource;
        }
        if (in_array($operation, [DataSourceCapabilities::IMPORT_DRY_RUN, DataSourceCapabilities::IMPORT_COMMIT], true)) {
            return $source instanceof WritableDataSource && $source instanceof FieldAwareDataSource;
        }
        if (in_array($operation, [DataSourceCapabilities::EXPORT_CSV, DataSourceCapabilities::EXPORT_XLSX], true)) {
            return $source instanceof QueryableDataSource && $source instanceof FieldAwareDataSource;
        }
        if (in_array($operation, [DataSourceCapabilities::MIGRATIONS, DataSourceCapabilities::ROLLBACK], true)) {
            return $source instanceof MigrationAwareDataSource;
        }

        return true;
    }
}
