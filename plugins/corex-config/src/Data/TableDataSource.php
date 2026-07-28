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
use Corex\Security\Upload\AttachmentDelivery;
use Corex\Data\DataSourceCapabilities;

/**
 * A DataSource over a Corex-managed custom table (spec 038): it exposes the table's declared
 * columns and shapes each row to `id` + exactly those columns (extra columns dropped, missing
 * ones defaulted to ''), so a managed table appears in Corex → Data like any other source with
 * no new UI. The `$wpdb` access lives in the injected reader, so this shaping is unit-tested.
 *
 * Capabilities are *derived* from the table's declaration rather than asserted here (spec 074), so
 * a table that declares nothing stays read-only and cannot advertise an operation it never agreed
 * to. Writes and migrations arrive through {@see WritableTableDataSource}, which is the subclass
 * the registry builds when — and only when — the declaration asks for them.
 */
class TableDataSource implements QueryableDataSource, SchemaAwareDataSource, CapabilityAwareDataSource, FieldAwareDataSource
{
    public function __construct(
        protected readonly ManagedTable $table,
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
    /**
     * The paged list.
     *
     * Shaped through {@see shapeRows()} like every other read path. It used to inline its own copy
     * of the same loop, which is how it missed the attachment hydration `query()` and `record()`
     * got: the Data screen's list showed `21848` where its detail modal showed a link, from the
     * same column on the same table. Two loops that must agree are one loop.
     */
    public function rows(int $page, int $perPage): array
    {
        return $this->shapeRows($this->reader->page(
            $this->table->name,
            $this->table->columnIds(),
            max(1, $page),
            max(1, $perPage),
        ));
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

    /**
     * The importable shape of this source.
     *
     * The type used to be hardcoded to text for every column, so a table that declared an
     * attachment column described itself as holding text and every surface downstream rendered the
     * id as a number (#138 item 6). It reads the declared type now, through the same allow-list
     * `fields()` uses, so the two cannot disagree about what a column is.
     */
    public function schema(): array
    {
        $writable = $this->writesDeclared();

        return array_map(
            function (array $column) use ($writable): array {
                $declared = $writable ? $this->table->writableField($column['id']) : null;

                return [
                    'name' => $column['label'],
                    'type' => self::fieldType($declared['type'] ?? DataField::TYPE_TEXT),
                ];
            },
            $this->table->displayColumns(),
        );
    }

    /**
     * Whether this source can honour writes. Always false here; the writable subclass says yes.
     *
     * A predicate rather than an `instanceof` check inside `capabilities()`, so the class that can
     * actually do the work is the one that claims it — and adding another kind of source later
     * means writing a subclass, not editing this method.
     */
    protected function writesDeclared(): bool
    {
        return false;
    }

    /** Whether this source can honour migrations. Always false here; see {@see writesDeclared()}. */
    protected function migrationsDeclared(): bool
    {
        return false;
    }

    /**
     * Everything this source can do, derived from the table's declaration.
     *
     * Write, import, and migration flags come from the declaration *and* from the class being one
     * that implements the matching interface. That makes the answer self-consistent by
     * construction: a source can never report a capability the services would then refuse to
     * honour, which was exactly the shape of the Import and Migrations dead ends this replaces.
     */
    public function capabilities(): DataSourceCapabilities
    {
        $writable   = $this->writesDeclared();
        $migratable = $this->migrationsDeclared();

        $permissions = [
            DataSourceCapabilities::READ       => CorexAbility::MANAGE_DATA,
            DataSourceCapabilities::QUERY      => CorexAbility::MANAGE_DATA,
            DataSourceCapabilities::DETAIL     => CorexAbility::MANAGE_DATA,
            DataSourceCapabilities::DELETE     => CorexAbility::MANAGE_DATA,
            DataSourceCapabilities::EXPORT_CSV => CorexAbility::MANAGE_DATA,
        ];

        if ($writable) {
            $permissions[DataSourceCapabilities::CREATE]         = CorexAbility::MANAGE_DATA_MODELS;
            $permissions[DataSourceCapabilities::UPDATE]         = CorexAbility::MANAGE_DATA_MODELS;
            $permissions[DataSourceCapabilities::IMPORT_DRY_RUN] = CorexAbility::MANAGE_DATA_MODELS;
            $permissions[DataSourceCapabilities::IMPORT_COMMIT]  = CorexAbility::MANAGE_DATA_MODELS;
        }

        if ($migratable) {
            $permissions[DataSourceCapabilities::MIGRATIONS] = CorexAbility::MANAGE_DATA_MODELS;

            if ($this->table->supportsRollback()) {
                $permissions[DataSourceCapabilities::ROLLBACK] = CorexAbility::MANAGE_DATA_MODELS;
            }
        }

        return new DataSourceCapabilities(
            sourceKey: $this->key(),
            read: true,
            query: true,
            schema: true,
            detail: true,
            create: $writable,
            update: $writable,
            delete: true,
            bulkUpdate: $writable,
            bulkDelete: false,
            importDryRun: $writable && $this->table->supportsImport(),
            importCommit: $writable && $this->table->supportsImport(),
            exportCsv: true,
            exportXlsx: false,
            migrations: $migratable,
            rollback: $migratable && $this->table->supportsRollback(),
            maxPageSize: 100,
            permissionMap: $permissions,
        );
    }

    /**
     * The field schema, with the declared writable fields marked writable and carrying the
     * table's own aliases and validation — which is what the import planner and validator read.
     */
    public function fields(): array
    {
        $writable = $this->writesDeclared();

        return array_map(
            function (array $column) use ($writable): DataField {
                $declared = $writable ? $this->table->writableField($column['id']) : null;

                return new DataField(
                    key: $column['id'],
                    label: $column['label'],
                    type: self::fieldType($declared['type'] ?? DataField::TYPE_TEXT),
                    required: (bool) ($declared['required'] ?? false),
                    // A required field cannot also be nullable, and a read-only one is always
                    // nullable as far as the import layer is concerned.
                    nullable: ! (bool) ($declared['required'] ?? false),
                    readOnly: $declared === null,
                    filterOperators: ['equals'],
                    sortable: true,
                    personalDataClass: DataField::PERSONAL_NONE,
                    validation: (array) ($declared['validation'] ?? []),
                    importAliases: (array) ($declared['aliases'] ?? []),
                );
            },
            $this->table->displayColumns(),
        );
    }

    /** A declared type CoreX does not model falls back to text rather than rejecting the table. */
    private static function fieldType(string $type): string
    {
        return in_array($type, [
            DataField::TYPE_TEXT,
            DataField::TYPE_TEXTAREA,
            DataField::TYPE_EMAIL,
            DataField::TYPE_TEL,
            DataField::TYPE_URL,
            DataField::TYPE_INTEGER,
            DataField::TYPE_DECIMAL,
            DataField::TYPE_BOOLEAN,
            DataField::TYPE_DATE,
            DataField::TYPE_DATETIME,
            DataField::TYPE_SELECT,
            DataField::TYPE_JSON,
            DataField::TYPE_ATTACHMENT,
        ], true) ? $type : DataField::TYPE_TEXT;
    }

    /** @param list<array<string,scalar>> $records @return list<array<string,scalar>> */
    private function shapeRows(array $records): array
    {
        return array_map($this->shapeRow(...), $records);
    }

    /** @param array<string,scalar> $record @return array<string,mixed> */
    private function shapeRow(array $record): array
    {
        $attachments = $this->attachmentColumns();

        $row = ['id' => $record['id'] ?? 0];
        foreach ($this->table->columnIds() as $column) {
            $value = $record[$column] ?? '';

            $row[$column] = in_array($column, $attachments, true)
                ? self::describeAttachment((int) $value)
                : $value;
        }

        return $row;
    }

    /**
     * @return list<string> the ids of columns this table declares as attachments
     */
    private function attachmentColumns(): array
    {
        $columns = [];

        foreach ($this->fields() as $field) {
            if ($field->type === DataField::TYPE_ATTACHMENT) {
                $columns[] = $field->key;
            }
        }

        return $columns;
    }

    /**
     * A stored file, described well enough for an admin surface to render it.
     *
     * Resolved here rather than in the browser: the row already goes over the wire, and a React
     * component fetching a name per attachment would turn one list request into one per row.
     *
     * A missing attachment reports itself as missing rather than as absent. An id that no longer
     * resolves means a file was deleted out from under a record, and an operator seeing an em dash
     * would read that as "nobody uploaded anything" (FR-008).
     *
     * @return array{id:int,name:string,url:string,missing:bool}|string
     */
    private static function describeAttachment(int $attachmentId): array|string
    {
        if ($attachmentId <= 0) {
            return '';
        }

        $path = get_attached_file($attachmentId);

        if ($path === false || $path === '') {
            return ['id' => $attachmentId, 'name' => '', 'url' => '', 'missing' => true];
        }

        return [
            'id'      => $attachmentId,
            'name'    => basename((string) $path),
            'url'     => AttachmentDelivery::url($attachmentId),
            'missing' => false,
        ];
    }
}
