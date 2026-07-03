<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Jobs;

defined('ABSPATH') || exit;

use DateTimeImmutable;
use RuntimeException;

/**
 * Idempotent enqueue, status, cancellation, and retry orchestration.
 */
final class JobService
{
    public function __construct(
        private readonly JobRepository $jobs,
        private readonly JobDispatcher $dispatcher,
    ) {
    }

    public function enqueue(
        string $kind,
        int $actorId,
        int $total,
        string $inputHash,
        DateTimeImmutable $now,
    ): BoundedJob {
        $existing = $this->jobs->findActive($kind, $inputHash);

        if ($existing !== null) {
            return $existing;
        }

        if (! $this->dispatcher->available()) {
            throw new RuntimeException('No bounded-job dispatcher is available.');
        }

        $job = $this->jobs->create(BoundedJob::queued($kind, $actorId, $total, $inputHash, $now));
        $this->dispatcher->dispatch($job);

        return $job;
    }

    public function find(int $id): ?BoundedJob
    {
        return $this->jobs->find($id);
    }

    public function cancel(int $id, DateTimeImmutable $now): ?BoundedJob
    {
        $job = $this->jobs->find($id);

        if ($job === null || $job->terminal()) {
            return null;
        }

        $cancelled = $job->cancel($now);
        $this->jobs->save($cancelled);
        $this->dispatcher->cancel($id);

        return $cancelled;
    }

    public function retry(int $id, DateTimeImmutable $now): ?BoundedJob
    {
        $job = $this->jobs->find($id);

        if ($job === null || ! in_array($job->state, [BoundedJob::STATE_FAILED, BoundedJob::STATE_PARTIAL], true)) {
            return null;
        }

        $queued = $job->retry($now);
        $this->jobs->save($queued);
        $this->dispatcher->dispatch($queued);

        return $queued;
    }
}
