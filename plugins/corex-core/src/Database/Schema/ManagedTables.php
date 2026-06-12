<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * The registry of tables an app has marked managed (opt-in). corex-config reads it to seed the
 * Corex → Data screen with a source per table — so a custom table appears in the admin like a
 * post type, with no admin UI code. Keyed by name, so re-registering a name replaces it.
 */
final class ManagedTables
{
    /** @var array<string,ManagedTable> */
    private array $tables = [];

    public function register(ManagedTable $table): void
    {
        $this->tables[$table->name] = $table;
    }

    /**
     * @return list<ManagedTable>
     */
    public function all(): array
    {
        return array_values($this->tables);
    }
}
