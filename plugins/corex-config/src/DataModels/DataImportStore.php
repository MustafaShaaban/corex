<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

interface DataImportStore
{
    public function create(DataImportRun $run): DataImportRun;

    public function find(int $id): ?DataImportRun;

    public function findByHash(string $inputHash): ?DataImportRun;

    public function attachJob(int $id, int $jobId): DataImportRun;

    public function finish(int $id, int $succeeded, int $failed): void;
}
