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
 */
final class SchemaComponent
{
    /** @param list<Table> $tables */
    public function __construct(
        public readonly string $id,
        public readonly string $version,
        public readonly array $tables,
        public readonly string $optionName,
    ) {
    }
}
