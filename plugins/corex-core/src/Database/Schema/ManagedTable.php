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
        private readonly ?string $textDomain = null,
    ) {
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
}
