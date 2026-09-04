<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * Carries enough per-site evidence for a network caller to continue after one
 * failure while still choosing a non-zero exit path (spec 100 FR-031, FR-032).
 */
final class SiteMigrationResult
{
    /**
     * @param list<string> $componentsMigrated
     * @param list<string> $tablesCreated
     */
    public function __construct(
        public readonly int $siteId,
        public readonly SiteMigrationOutcome $outcome,
        public readonly array $componentsMigrated = [],
        public readonly array $tablesCreated = [],
        public readonly ?string $error = null,
        public readonly float $durationMs = 0.0,
    ) {
    }

    public function succeeded(): bool
    {
        return $this->outcome === SiteMigrationOutcome::Migrated
            || $this->outcome === SiteMigrationOutcome::AlreadyCurrent;
    }
}
