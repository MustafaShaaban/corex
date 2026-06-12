<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * Marks a Corex custom table as "managed" so it appears in the Corex → Data admin screen — the
 * unprefixed table name, a human label, and the ordered columns to show ({id, label}). Pure: it
 * carries the metadata; corex-config turns it into a DataSource (spec 038).
 */
final class ManagedTable
{
    /**
     * @param list<array{id:string,label:string}> $columns
     */
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly array $columns,
    ) {
    }

    /**
     * @return list<string>
     */
    public function columnIds(): array
    {
        return array_map(static fn (array $c): string => $c['id'], $this->columns);
    }
}
