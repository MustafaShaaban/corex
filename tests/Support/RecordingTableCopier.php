<?php

/**
 * A database in memory for the release installer's table copies: tables by name with their
 * rows, and a list of everything it was asked to do (spec 107, FR-034).
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Config\Releases\ReleaseTableCopier;

final class RecordingTableCopier implements ReleaseTableCopier
{
    /** @var list<string> What was asked, in order: `copy a > b`, `drop a`. */
    public array $asked = [];

    /** A table whose copy fails, as one too large for the request does. */
    public ?string $failsOn = null;

    /**
     * @param array<string,list<string>> $tables Each table's name and its rows.
     */
    public function __construct(public array $tables)
    {
    }

    public function tablesStartingWith(string $prefix): array
    {
        return array_values(array_filter(
            array_keys($this->tables),
            static fn (string $table): bool => str_starts_with($table, $prefix),
        ));
    }

    public function exists(string $table): bool
    {
        return isset($this->tables[$table]);
    }

    public function copy(string $from, string $to): bool
    {
        $this->asked[] = "copy $from > $to";

        if ($from === $this->failsOn) {
            return false;
        }

        $this->tables[$to] = $this->tables[$from];

        return true;
    }

    public function drop(string $table): void
    {
        $this->asked[] = "drop $table";
        unset($this->tables[$table]);
    }
}
