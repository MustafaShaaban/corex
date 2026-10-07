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

    /**
     * Records where a finished export's file is.
     *
     * @param array{path:string,extension:string,content_type:string,subject:string} $file
     */
    public function saveFile(int $runId, array $file, int $recordCount): void;

    /**
     * @return array{path:string,extension:string,content_type:string,subject:string}|null Null while
     *         the export is not finished, and for an export made before files were kept on disk.
     */
    public function file(int $runId): ?array;

    /**
     * The CSV text of an export made before files were kept on disk (spec 068). Nothing writes
     * one any more; this is how those that exist are still downloaded.
     */
    public function artifact(int $runId): ?string;
}
