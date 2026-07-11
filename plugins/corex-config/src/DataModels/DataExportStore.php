<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;
interface DataExportStore
{
    public function create(DataExportRun $run): DataExportRun;
    public function attachJob(int $id, int $jobId): DataExportRun;
    public function find(int $id): ?DataExportRun;
    public function findByHash(string $hash): ?DataExportRun;
    /** @return list<DataExportRun> */
    public function history(int $actorId, bool $manageAll, int $limit): array;
    public function saveArtifact(int $id, string $artifact): void;
    public function artifact(int $id): ?string;
    public function finish(int $id, int $rows): void;
}
