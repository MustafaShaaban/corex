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

    /**
     * Several versions from one read of the store, where the store can make one.
     *
     * A request may be answered from what it has already read, as `get()` may. A caller
     * that must have what is stored now calls `forget()` first.
     *
     * @param list<string> $optionNames
     *
     * @return array<string, string> The version by option name; an empty string where none is stored.
     */
    public function getMany(array $optionNames): array;

    /**
     * Drops what this request remembers of these versions, so the next read is of the store.
     *
     * Another request may have written a version since this one read it. The migration
     * runner forgets before it decides what a run has to do (DECISIONS #360).
     *
     * @param list<string> $optionNames
     */
    public function forget(array $optionNames): void;

    public function put(string $optionName, string $version): void;
}
