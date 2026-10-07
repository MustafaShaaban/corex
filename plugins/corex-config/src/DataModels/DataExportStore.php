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
    /**
     * Records where a finished export's file is.
     *
     * @param array{path:string,extension:string,content_type:string} $file
     */
    public function saveFile(int $id, array $file): void;

    /**
     * @return array{path:string,extension:string,content_type:string}|null Null while the export
     *         is not finished, when its file is no longer on disk, and for an export made before
     *         files were kept on disk.
     */
    public function file(int $id): ?array;

    /**
     * The bytes of an export made before files were kept on disk (spec 068). Nothing writes one
     * any more; this is how those that exist are still downloaded.
     */
    public function artifact(int $id): ?string;
    public function finish(int $id, int $rows): void;
}
