<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * Separates version decisions from WordPress option persistence so migration
 * ordering and retry behavior can be proved headlessly (spec 100 FR-029).
 */
interface SchemaVersionStore
{
    public function get(string $optionName): string;

    public function put(string $optionName, string $version): void;
}
