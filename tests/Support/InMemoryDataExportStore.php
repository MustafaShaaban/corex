<?php

/**
 * Data export runs held in memory, for the tests of what is built on the store.
 *
 * A run's file is whatever path the test's writers wrote to, kept beside the run; nothing here
 * touches WordPress.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Config\DataModels\DataExportRun;
use Corex\Config\DataModels\DataExportStore;
use DateTimeImmutable;

final class InMemoryDataExportStore implements DataExportStore
{
    /** @var array<int,DataExportRun> */
    public array $runs = [];

    /** @var array<int,array{path:string,extension:string,content_type:string}> */
    public array $files = [];

    /** @var array<int,string> */
    public array $artifacts = [];

    public function create(DataExportRun $run): DataExportRun
    {
        $run = $run->withId(count($this->runs) + 1);

        return $this->runs[$run->id] = $run;
    }

    public function attachJob(int $id, int $jobId): DataExportRun
    {
        return $this->runs[$id] = $this->runs[$id]->withJob($jobId);
    }

    public function find(int $id): ?DataExportRun
    {
        return $this->runs[$id] ?? null;
    }

    public function findByHash(string $hash): ?DataExportRun
    {
        foreach ($this->runs as $run) {
            if ($run->inputHash === $hash) {
                return $run;
            }
        }

        return null;
    }

    public function history(int $actorId, bool $manageAll, int $limit): array
    {
        return array_slice(array_values(array_filter(
            $this->runs,
            static fn (DataExportRun $run): bool => $manageAll || $run->actorId === $actorId,
        )), 0, $limit);
    }

    public function leaveOut(int $id, string $sourceKey): DataExportRun
    {
        return $this->runs[$id] = $this->runs[$id]->leavingOut($sourceKey);
    }

    public function saveFile(int $id, array $file): void
    {
        $this->files[$id] = $file;
        $this->runs[$id] = $this->runs[$id]->withFileOf(is_file($file['path']) ? (int) filesize($file['path']) : 0);
    }

    public function file(int $id): ?array
    {
        return $this->files[$id] ?? null;
    }

    public function artifact(int $id): ?string
    {
        return $this->artifacts[$id] ?? null;
    }

    public function finish(int $id, int $rows): void
    {
        $this->runs[$id] = $this->runs[$id]->completed($rows);
    }

    public function removeFile(int $id, string $reason, int $actorId): DataExportRun
    {
        if (isset($this->files[$id]) && is_file($this->files[$id]['path'])) {
            unlink($this->files[$id]['path']);
        }
        unset($this->files[$id], $this->artifacts[$id]);

        return $this->runs[$id] = $this->runs[$id]->withoutFile($reason, $actorId, new DateTimeImmutable('now'));
    }

    public function holdingFilesBefore(DateTimeImmutable $cutoff, int $limit): array
    {
        return array_slice(array_values(array_filter(
            $this->runs,
            fn (DataExportRun $run): bool => isset($this->files[$run->id]) && $run->createdAt < $cutoff,
        )), 0, $limit);
    }
}
