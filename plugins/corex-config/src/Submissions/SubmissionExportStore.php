<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DateTimeImmutable;

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
     *         the export is not finished, when its file is no longer on disk, and for an export
     *         made before files were kept on disk.
     */
    public function file(int $runId): ?array;

    /**
     * Removes an export's file and keeps its entry, which then says why the file went.
     *
     * @param string $reason  One of `SubmissionExportRun::REMOVED_*`.
     * @param int    $actorId The person who deleted it; 0 when it expired.
     */
    public function removeFile(int $runId, string $reason, int $actorId): SubmissionExportRun;

    /**
     * @return list<SubmissionExportRun> Exports made before the cutoff that still hold a file,
     *         oldest first, and no more than the limit.
     */
    public function holdingFilesBefore(DateTimeImmutable $cutoff, int $limit): array;

    /**
     * The CSV text of an export made before files were kept on disk (spec 068). Nothing writes
     * one any more; this is how those that exist are still downloaded.
     */
    public function artifact(int $runId): ?string;
}
