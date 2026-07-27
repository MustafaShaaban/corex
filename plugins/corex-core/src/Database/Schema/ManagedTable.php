<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

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
 */
final class ManagedTable
{
    /** A CSV column that matches no declared field stops the import. The safe default. */
    public const UNKNOWN_REJECT = 'reject';

    /** A CSV column that matches no declared field is dropped, and the import continues. */
    public const UNKNOWN_IGNORE = 'ignore';

    /** @var list<string> */
    private const UNKNOWN_POLICIES = [self::UNKNOWN_REJECT, self::UNKNOWN_IGNORE];

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
    ) {
        if (! in_array($this->unknownColumns, self::UNKNOWN_POLICIES, true)) {
            throw new InvalidArgumentException('Managed table unknown-column policy is invalid.');
        }

        $this->writable = $this->normalizeWritable($writableFields);

        foreach ($this->migrations as $migration) {
            self::assertMigration($migration);
        }
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
