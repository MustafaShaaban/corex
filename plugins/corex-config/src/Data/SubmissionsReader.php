<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * Reads stored form submissions for the Data screen. Split from SubmissionsSource so the
 * row-shaping is unit-testable with a stub while the WP_Query/meta access stays at the
 * boundary (WpSubmissionsReader).
 */
interface SubmissionsReader
{
    /**
     * @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}>
     */
    public function page(int $page, int $perPage): array;

    public function total(): int;

    public function trash(int $id): bool;
}
