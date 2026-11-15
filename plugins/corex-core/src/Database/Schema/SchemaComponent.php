<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * The option name is explicit because deriving it from the component id would
 * change the existing key and rerun dbDelta on every installed table (FR-033).
 *
 * A component may also carry data steps: work on its stored rows that a version needs, run after
 * its tables exist and before its version is stored (see {@see DataMigrationStep}).
 */
final class SchemaComponent
{
    /**
     * @param list<Table>             $tables
     * @param list<DataMigrationStep> $steps
     */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly array $tables,
        public readonly string $optionName,
        public readonly array $steps = [],
    ) {
    }
}
