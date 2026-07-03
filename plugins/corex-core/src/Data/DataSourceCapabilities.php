<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Data;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Immutable, granular capability declaration for one registered data source.
 */
final class DataSourceCapabilities
{
    public const READ            = 'read';
    public const QUERY           = 'query';
    public const SCHEMA          = 'schema';
    public const DETAIL          = 'detail';
    public const CREATE          = 'create';
    public const UPDATE          = 'update';
    public const DELETE          = 'delete';
    public const BULK_UPDATE     = 'bulk_update';
    public const BULK_DELETE     = 'bulk_delete';
    public const IMPORT_DRY_RUN  = 'import_dry_run';
    public const IMPORT_COMMIT   = 'import_commit';
    public const EXPORT_CSV      = 'export_csv';
    public const EXPORT_XLSX     = 'export_xlsx';
    public const MIGRATIONS      = 'migrations';
    public const ROLLBACK        = 'rollback';

    /** @var list<string> */
    private const KEYS = [
        self::READ,
        self::QUERY,
        self::SCHEMA,
        self::DETAIL,
        self::CREATE,
        self::UPDATE,
        self::DELETE,
        self::BULK_UPDATE,
        self::BULK_DELETE,
        self::IMPORT_DRY_RUN,
        self::IMPORT_COMMIT,
        self::EXPORT_CSV,
        self::EXPORT_XLSX,
        self::MIGRATIONS,
        self::ROLLBACK,
    ];

    /** @param array<string,string> $permissionMap */
    public function __construct(
        public readonly string $sourceKey,
        public readonly bool $read,
        public readonly bool $query,
        public readonly bool $schema,
        public readonly bool $detail,
        public readonly bool $create,
        public readonly bool $update,
        public readonly bool $delete,
        public readonly bool $bulkUpdate,
        public readonly bool $bulkDelete,
        public readonly bool $importDryRun,
        public readonly bool $importCommit,
        public readonly bool $exportCsv,
        public readonly bool $exportXlsx,
        public readonly bool $migrations,
        public readonly bool $rollback,
        public readonly int $maxPageSize,
        public readonly array $permissionMap,
    ) {
        if (preg_match('/^[a-z][a-z0-9-]*$/', $this->sourceKey) !== 1) {
            throw new InvalidArgumentException('Data source key is invalid.');
        }

        if ($this->maxPageSize < 1 || $this->maxPageSize > 500) {
            throw new InvalidArgumentException('Data source page size must be between 1 and 500.');
        }

        foreach ($this->permissionMap as $operation => $ability) {
            if (! in_array($operation, self::KEYS, true) || preg_match('/^corex_[a-z0-9_]+$/', $ability) !== 1) {
                throw new InvalidArgumentException('Data source permission map is invalid.');
            }
        }
    }

    public function supports(string $operation): bool
    {
        return match ($operation) {
            self::READ           => $this->read,
            self::QUERY          => $this->query,
            self::SCHEMA         => $this->schema,
            self::DETAIL         => $this->detail,
            self::CREATE         => $this->create,
            self::UPDATE         => $this->update,
            self::DELETE         => $this->delete,
            self::BULK_UPDATE    => $this->bulkUpdate,
            self::BULK_DELETE    => $this->bulkDelete,
            self::IMPORT_DRY_RUN => $this->importDryRun,
            self::IMPORT_COMMIT  => $this->importCommit,
            self::EXPORT_CSV     => $this->exportCsv,
            self::EXPORT_XLSX    => $this->exportXlsx,
            self::MIGRATIONS     => $this->migrations,
            self::ROLLBACK       => $this->rollback,
            default              => false,
        };
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'source_key'      => $this->sourceKey,
            'read'            => $this->read,
            'query'           => $this->query,
            'schema'          => $this->schema,
            'detail'          => $this->detail,
            'create'          => $this->create,
            'update'          => $this->update,
            'delete'          => $this->delete,
            'bulk_update'     => $this->bulkUpdate,
            'bulk_delete'     => $this->bulkDelete,
            'import_dry_run'  => $this->importDryRun,
            'import_commit'   => $this->importCommit,
            'export_csv'      => $this->exportCsv,
            'export_xlsx'     => $this->exportXlsx,
            'migrations'      => $this->migrations,
            'rollback'        => $this->rollback,
            'max_page_size'   => $this->maxPageSize,
            'permission_map'  => $this->permissionMap,
        ];
    }
}
