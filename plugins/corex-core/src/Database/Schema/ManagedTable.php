<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

use Corex\Data\DataField;
use InvalidArgumentException;

/**
 * Marks a Corex custom table as "managed" so it appears in the Corex → Data admin screen — the
 * unprefixed table name, a human label, and the ordered columns to show ({id, label}). Pure: it
 * carries the metadata; corex-config turns it into a DataSource (spec 038).
 *
 * A table may also declare what may be *done* to it (spec 074): which fields are writable, the CSV
 * headers that map to them, how to validate them, what to do with a column nobody declared, and
 * which schema migrations it ships. Everything here is opt-in and read-only by default — the Data
 * workspace derives its capabilities from this declaration, so a table cannot end up advertising an
 * operation it never agreed to. Audit and system tables simply say nothing and stay read-only.
 *
 * Migrations are declared as plain arrays rather than `MigrationDefinition` objects because that
 * class lives in corex-config, and the schema layer must not depend on the admin layer.
 *
 * A table also says which of its columns hold personal data, and of which kind (spec 109). That
 * one is not opt-in in its effect: a column the table says nothing about is "undeclared", and
 * everything that lets a record leave the site treats an undeclared column as personal data.
 */
final class ManagedTable
{
    /** A CSV column that matches no declared field stops the import. The safe default. */
    public const UNKNOWN_REJECT = 'reject';

    /** A CSV column that matches no declared field is dropped, and the import continues. */
    public const UNKNOWN_IGNORE = 'ignore';

    /** @var list<string> */
    private const UNKNOWN_POLICIES = [self::UNKNOWN_REJECT, self::UNKNOWN_IGNORE];

    /** What a site receives or keeps for its work: subscribers, applications, a site's own models. */
    public const CATEGORY_RECORDS = 'records';

    /** The site's own history: the activity log, login attempts, jobs, notifications. */
    public const CATEGORY_LOG = 'log';

    /** @var list<array{id:string,type:string,required:bool,aliases:list<string>,validation:array<string,mixed>}> */
    private array $writable;

    /**
     * This is an immutable declaration record, not a service: every parameter is a piece of the
     * same statement about one table, and they are passed by name at the single call site each
     * table has. (Deliberate exception to the four-argument ceiling, which exists to stop
     * collaborator lists growing — there are no collaborators here.)
     *
     * @param list<array{id:string,label:string}> $columns
     * @param list<array<string,mixed>>           $writableFields Empty (the default) means read-only.
     * @param list<array<string,mixed>>           $migrations     Empty (the default) means no migrations.
     * @param array<string,scalar>                $insertDefaults Values for columns an import cannot write.
     * @param array<string,string>                $personalData   A column's id to one of the
     *                                                            personal-data classes of `DataField`.
     *                                                            Empty (the default) leaves every
     *                                                            column undeclared.
     * @param string|null                         $category       One of the `CATEGORY_*`; which
     *                                                            group the Data screen lists the
     *                                                            table in. Null (the default) is
     *                                                            records for a table that can be
     *                                                            written to, and a log otherwise.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly array $columns,
        private readonly ?string $textDomain = null,
        array $writableFields = [],
        public readonly string $unknownColumns = self::UNKNOWN_REJECT,
        public readonly array $migrations = [],
        public readonly array $insertDefaults = [],
        private readonly array $personalData = [],
        private readonly ?string $category = null,
    ) {
        if (! in_array($this->unknownColumns, self::UNKNOWN_POLICIES, true)) {
            throw new InvalidArgumentException('Managed table unknown-column policy is invalid.');
        }

        $this->writable = $this->normalizeWritable($writableFields);

        foreach ($this->migrations as $migration) {
            self::assertMigration($migration);
        }

        $this->assertPersonalData();

        if ($this->category !== null && ! in_array($this->category, [self::CATEGORY_RECORDS, self::CATEGORY_LOG], true)) {
            throw new InvalidArgumentException(sprintf('Managed table "%s" declares a category that is neither records nor a log.', $this->name));
        }
    }

    /**
     * Which group the Data screen lists the table in (spec 109, FR-003).
     *
     * Most tables say nothing and the answer follows from what they are: a table somebody can
     * write to holds what the site works with, and one nobody can is a record of what happened.
     * A table that is read-only and still the site's own records says so.
     */
    public function category(): string
    {
        return $this->category ?? ($this->isWritable() ? self::CATEGORY_RECORDS : self::CATEGORY_LOG);
    }

    /** The class the table declared for a column, or "undeclared" when it declared none. */
    public function personalDataClass(string $columnId): string
    {
        return $this->personalData[$columnId] ?? DataField::PERSONAL_UNDECLARED;
    }

    public function displayLabel(): string
    {
        return $this->textDomain === null ? $this->label : __($this->label, $this->textDomain);
    }

    /** @return list<array{id:string,label:string}> */
    public function displayColumns(): array
    {
        if ($this->textDomain === null) {
            return $this->columns;
        }

        return array_map(
            fn (array $column): array => [
                'id'    => $column['id'],
                'label' => __($column['label'], $this->textDomain),
            ],
            $this->columns,
        );
    }

    /**
     * @return list<string>
     */
    public function columnIds(): array
    {
        return array_map(static fn (array $c): string => $c['id'], $this->columns);
    }

    /**
     * The columns a create must set even though no one may import into them, and their values.
     *
     * A row created by import has to end up in the same shape the owning feature would have
     * created — an imported newsletter subscriber with no `status` is in a state the double opt-in
     * flow does not recognise, so it can never be confirmed and never be emailed. Defaults are the
     * feature's answer to "what does a new row look like", kept separate from the writable fields
     * precisely so an import still cannot choose them.
     *
     * @return array<string,scalar>
     */
    public function insertDefaults(): array
    {
        $columnIds = $this->columnIds();
        $writable  = $this->writableFieldIds();
        $defaults  = [];

        foreach ($this->insertDefaults as $column => $value) {
            $column = (string) $column;

            if (in_array($column, $columnIds, true) && ! in_array($column, $writable, true)) {
                $defaults[$column] = $value;
            }
        }

        return $defaults;
    }

    /** @return list<string> */
    public function writableFieldIds(): array
    {
        return array_map(static fn (array $field): string => $field['id'], $this->writable);
    }

    /** @return array{id:string,type:string,required:bool,aliases:list<string>,validation:array<string,mixed>}|null */
    public function writableField(string $id): ?array
    {
        foreach ($this->writable as $field) {
            if ($field['id'] === $id) {
                return $field;
            }
        }

        return null;
    }

    public function isWritable(): bool
    {
        return $this->writable !== [];
    }

    /**
     * Import needs somewhere to put the rows, so it is exactly writability — there is no separate
     * switch to get out of step with it.
     */
    public function supportsImport(): bool
    {
        return $this->isWritable();
    }

    public function supportsMigrations(): bool
    {
        return $this->migrations !== [];
    }

    /**
     * Rollback is claimed only when at least one declaration actually provides a way back. Offering
     * it for a one-way migration would promise an undo that does nothing; the per-run check in
     * MigrationService still gates each individual definition.
     */
    public function supportsRollback(): bool
    {
        foreach ($this->migrations as $migration) {
            if (($migration['rollback_supported'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<array<string,mixed>> $fields
     * @return list<array{id:string,type:string,required:bool,aliases:list<string>,validation:array<string,mixed>}>
     */
    private function normalizeWritable(array $fields): array
    {
        $columnIds = $this->columnIds();
        $clean     = [];

        foreach ($fields as $field) {
            $id = (string) ($field['id'] ?? '');

            // Writing a column the table does not declare would be a silent schema mismatch, so it
            // is a construction error rather than something to discover at import time.
            if (! in_array($id, $columnIds, true)) {
                throw new InvalidArgumentException(
                    sprintf('Managed table "%s" declares writable field "%s", which is not one of its columns.', $this->name, $id),
                );
            }

            $clean[] = [
                'id'         => $id,
                'type'       => (string) ($field['type'] ?? 'text'),
                'required'   => (bool) ($field['required'] ?? false),
                'aliases'    => self::normalizeAliases($field['aliases'] ?? []),
                'validation' => (array) ($field['validation'] ?? []),
            ];
        }

        return $clean;
    }

    /**
     * Alias headers are folded to the canonical key shape so a CSV column matches whatever case,
     * spacing, or punctuation the exporting system happened to use.
     *
     * @param  mixed $aliases
     * @return list<string>
     */
    private static function normalizeAliases(mixed $aliases): array
    {
        $clean = [];

        foreach ((array) $aliases as $alias) {
            $normalized = strtolower(trim((string) $alias));
            $normalized = (string) preg_replace('/[\s]+/', '_', $normalized);
            $normalized = (string) preg_replace('/[^a-z0-9_-]/', '', $normalized);

            if ($normalized !== '' && ! in_array($normalized, $clean, true)) {
                $clean[] = $normalized;
            }
        }

        return $clean;
    }

    /**
     * A class for a column the table does not have is a declaration about nothing, and a class
     * nothing knows would be read as personal data forever without anybody learning why. Both
     * are construction errors, like a writable field that is not a column.
     */
    private function assertPersonalData(): void
    {
        $columnIds = $this->columnIds();

        foreach ($this->personalData as $columnId => $class) {
            if (! in_array((string) $columnId, $columnIds, true)) {
                throw new InvalidArgumentException(sprintf(
                    'Managed table "%s" declares personal data for "%s", which is not one of its columns.',
                    $this->name,
                    (string) $columnId,
                ));
            }

            if (! is_string($class) || ! DataField::isDeclarablePersonalClass($class)) {
                throw new InvalidArgumentException(sprintf(
                    'Managed table "%s" declares an unknown personal-data class for "%s".',
                    $this->name,
                    (string) $columnId,
                ));
            }
        }
    }

    /** @param array<string,mixed> $migration */
    private static function assertMigration(array $migration): void
    {
        $plan = (array) ($migration['plan'] ?? []);

        if ((string) ($migration['key'] ?? '') === ''
            || (string) ($migration['version'] ?? '') === ''
            || (string) ($migration['description'] ?? '') === ''
            || $plan === []) {
            throw new InvalidArgumentException('Managed table migration declaration is incomplete.');
        }
    }
}
