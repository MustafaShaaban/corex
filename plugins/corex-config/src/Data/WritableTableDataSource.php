<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Config\DataModels\ManagedTableMigrationProvider;
use Corex\Config\DataModels\MigrationAwareDataSource;
use Corex\Config\DataModels\MigrationProvider;
use Corex\Config\DataModels\TableMigrationRunner;
use Corex\Data\DataWriteAdapter;
use Corex\Database\Schema\ManagedTable;

/**
 * The managed-table source for a table that declared it may be written to (spec 074).
 *
 * Writability has to be a *type* difference rather than a flag, because the services ask
 * `instanceof WritableDataSource` / `instanceof MigrationAwareDataSource`. A single class carrying
 * a boolean would answer yes for every managed table — including the audit tables — which is the
 * failure this design exists to make impossible. The registry builds this subclass only when the
 * declaration asks for it, and {@see TableDataSource::capabilities()} derives its flags from both
 * the declaration and the class, so the two cannot disagree.
 */
final class WritableTableDataSource extends TableDataSource implements WritableDataSource, MigrationAwareDataSource
{
    public function __construct(
        ManagedTable $table,
        TableDataReader $reader,
        private readonly TableDataWriter $writer,
        private readonly TableMigrationRunner $migrations,
    ) {
        parent::__construct($table, $reader);
    }

    protected function writesDeclared(): bool
    {
        return $this->table->isWritable();
    }

    protected function migrationsDeclared(): bool
    {
        return $this->table->supportsMigrations();
    }

    public function writeAdapter(): DataWriteAdapter
    {
        return new ManagedTableWriteAdapter($this->table, $this->writer);
    }

    public function migrationProvider(): MigrationProvider
    {
        return new ManagedTableMigrationProvider($this->table, $this->migrations);
    }
}
