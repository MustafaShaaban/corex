<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Jobs;

defined('ABSPATH') || exit;

interface JobRepository
{
    public function create(BoundedJob $job): BoundedJob;

    public function find(int $id): ?BoundedJob;

    public function findActive(string $kind, string $inputHash): ?BoundedJob;

    public function save(BoundedJob $job): void;
}
