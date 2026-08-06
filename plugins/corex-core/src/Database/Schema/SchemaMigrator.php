<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Database\Schema;

defined('ABSPATH') || exit;

/**
 * Keeps schema orchestration headless-testable even though the WordPress migrator
 * loads upgrade.php and delegates table changes to dbDelta (spec 100 FR-030).
 */
interface SchemaMigrator
{
    public function fullName(string $name): string;

    public function create(Table $table): void;

    public function drop(string $name): void;

    public function exists(string $name): bool;
}
