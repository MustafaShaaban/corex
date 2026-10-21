<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Access\AccessRefusalRecorder;
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

    public function __construct(
        private DataRegistry $registry,
        private DataAccessPolicy $access,
        private ?AccessRefusalRecorder $refusals = null,
    ) {
    }

    /**
     * Every source this person may see. One whose records belong to a screen they may not open is
     * not listed at all (spec 112, FR-029).
     *
     * @return list<array<string,mixed>>
     */
    public function catalog(int $actorId): array
    {
        return array_values(array_map(
            fn (DataSource $source): array => $this->describe($actorId, $source->key()),
            array_filter(
                $this->registry->all(),
                fn (DataSource $source): bool => $this->withheldFrom($actorId, $source->key()) === null,
            ),
        ));
    }

    /**
     * The ability this person lacks to read this source at all: the ability of the screen its
     * records belong to. Null when nothing is withheld.
     */
    public function withheldFrom(int $actorId, string $sourceKey): ?string
    {
        $source = $this->source($sourceKey);
        $owner  = $source instanceof RestrictedDataSource ? $source->ownerAbility() : null;

        return $owner !== null && ! $this->access->allows($actorId, $owner) ? $owner : null;
    }

    public function label(string $sourceKey): string
    {
        return $this->source($sourceKey)->label();
    }

    /** @return array<string,mixed> */
    public function describe(int $actorId, string $sourceKey): array
    {
        $this->assertNotWithheld($actorId, $sourceKey);
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
            'category' => $source instanceof CategorisedDataSource ? $source->category() : CategorisedDataSource::RECORDS,
            'access' => $readable ? 'allowed' : 'denied',
            'capabilities' => $capabilities->toArray(),
            'actions' => $actions,
            // The fields below still carry their classes for a person without this: the screen
            // says which columns it is withholding, and the export refuses them either way.
            'permissions' => ['personal_data' => $this->mayExportPersonalData($actorId)],
            'fields' => $readable ? array_map(
                static fn (DataField $field): array => $field->toArray(),
                $this->registry->fields($sourceKey),
            ) : [],
        ];
    }

    /**
     * Whether this person may take personal data out of the site in a file (spec 109, D3).
     *
     * It is asked apart from what a source's operations ask. Being allowed to export a model is
     * being allowed to export what in it is not about a person.
     */
    public function mayExportPersonalData(int $actorId): bool
    {
        return $this->access->allows($actorId, CorexAbility::EXPORT_PERSONAL_DATA);
    }

    public function authorize(int $actorId, string $sourceKey, string $operation): DataSource
    {
        $this->assertNotWithheld($actorId, $sourceKey);
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

    /** Records in the access audit that this person was refused this source (FR-031). */
    public function noteWithheld(int $actorId, string $sourceKey): void
    {
        $this->refusals?->refused($actorId, 'data', $sourceKey);
    }

    /** Refuses, and records the refusal of, what is withheld. */
    private function assertNotWithheld(int $actorId, string $sourceKey): void
    {
        if ($this->withheldFrom($actorId, $sourceKey) === null) {
            return;
        }

        $this->noteWithheld($actorId, $sourceKey);

        throw DataAccessRestricted::forModel();
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
            return $source instanceof ExportableDataSource;
        }
        if (in_array($operation, [DataSourceCapabilities::MIGRATIONS, DataSourceCapabilities::ROLLBACK], true)) {
            return $source instanceof MigrationAwareDataSource;
        }

        return true;
    }
}
