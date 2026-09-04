<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * Keeps pagination state beside the per-site evidence so callers can resume a
 * large network sweep without hiding prior failures (spec 100 FR-028, FR-031).
 */
final class NetworkMigrationResult
{
    /** @param list<SiteMigrationResult> $results */
    public function __construct(
        public readonly array $results,
        public readonly int $nextOffset = 0,
        public readonly bool $complete = true,
    ) {
    }

    public function migrated(): int
    {
        return $this->count(SiteMigrationOutcome::Migrated);
    }

    public function failed(): int
    {
        return $this->count(SiteMigrationOutcome::Failed);
    }

    public function skipped(): int
    {
        return $this->count(SiteMigrationOutcome::Skipped);
    }

    /** @return list<SiteMigrationResult> */
    public function failures(): array
    {
        return array_values(array_filter(
            $this->results,
            static fn (SiteMigrationResult $result): bool => $result->outcome === SiteMigrationOutcome::Failed,
        ));
    }

    private function count(SiteMigrationOutcome $outcome): int
    {
        return count(array_filter(
            $this->results,
            static fn (SiteMigrationResult $result): bool => $result->outcome === $outcome,
        ));
    }
}
