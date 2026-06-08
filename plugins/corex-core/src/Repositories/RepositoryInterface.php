<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Repositories;

defined('ABSPATH') || exit;

use Corex\Models\Model;

/**
 * The data-access contract for an entity. Implementations are the only layer that
 * talks to the data source (spec FR-004). `query()` is added by the QueryBuilder
 * story (US3).
 */
interface RepositoryInterface
{
    public function find(int $id): ?Model;          // null when absent (FR-005)

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): Model;

    /** @param array<string, mixed> $attributes */
    public function update(int $id, array $attributes): Model;

    public function delete(int $id): bool;
}
