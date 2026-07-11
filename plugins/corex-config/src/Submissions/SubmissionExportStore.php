<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

interface SubmissionExportStore
{
    public function create(SubmissionExportRun $run): SubmissionExportRun;

    public function attachJob(int $runId, int $jobId): SubmissionExportRun;

    public function find(int $runId): ?SubmissionExportRun;

    public function findByHash(string $inputHash): ?SubmissionExportRun;

    /** @return list<SubmissionExportRun> */
    public function history(SubmissionAccessScope $scope, int $limit): array;

    public function saveArtifact(int $runId, string $csv, int $recordCount): void;

    public function artifact(int $runId): ?string;
}
