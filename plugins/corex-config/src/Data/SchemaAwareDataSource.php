<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * An optional capability: a data source that can describe its real field-level schema
 * (name + meaningful type) rather than only its table columns. The Data screen renders this
 * in the schema panel. Sources that do not implement it fall back to their table columns.
 */
interface SchemaAwareDataSource
{
    /**
     * The source's fields, derived from real data (never invented).
     *
     * @return list<array{name:string,type:string}>
     */
    public function schema(): array;
}
