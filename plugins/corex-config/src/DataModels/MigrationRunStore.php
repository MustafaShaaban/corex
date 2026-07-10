<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;
interface MigrationRunStore
{
    public function create(MigrationRun $run): MigrationRun;
    public function attachJob(int $id, int $jobId): MigrationRun;
    public function find(int $id): ?MigrationRun;
    public function findByHash(string $hash): ?MigrationRun;
    public function finish(int $id, string $state, string $message): void;
    /** @return list<MigrationRun> */
    public function history(int $actorId, bool $manageAll, int $limit): array;
}
