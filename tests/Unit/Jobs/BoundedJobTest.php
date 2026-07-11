<?php

/**
 * Unit tests for bounded, resumable, idempotent jobs (spec 068: shared foundation).
 *
 * @package Corex\Tests\Unit\Jobs
 */

declare(strict_types=1);

use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobDispatcher;
use Corex\Jobs\JobRepository;
use Corex\Jobs\JobService;

it('moves through queued running and completed states with bounded counters', function () {
    $created = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $job     = BoundedJob::queued(
        kind: 'data.export',
        actorId: 7,
        total: 10,
        inputHash: hash('sha256', 'export:submissions'),
        createdAt: $created,
    )->withId(14);

    $running = $job->start($created->modify('+1 second'));
    $next    = $running->advance(
        cursor: 'page:2',
        processed: 10,
        succeeded: 9,
        failed: 1,
        nextRunAt: $created->modify('+5 seconds'),
        updatedAt: $created->modify('+2 seconds'),
    );
    $done = $next->complete('artifact:exports/14.csv', $created->modify('+6 seconds'));

    expect($job->state)->toBe(BoundedJob::STATE_QUEUED)
        ->and($running->state)->toBe(BoundedJob::STATE_RUNNING)
        ->and($next->processed)->toBe(10)
        ->and($next->succeeded)->toBe(9)
        ->and($next->failed)->toBe(1)
        ->and($done->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($done->resultArtifact)->toBe('artifact:exports/14.csv')
        ->and($done->finishedAt)->not->toBeNull();
});

it('supports terminal cancellation without mutating the original', function () {
    $now       = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $queued    = BoundedJob::queued('models.import', 7, 100, hash('sha256', 'import:a'), $now);
    $cancelled = $queued->cancel($now->modify('+1 second'));

    expect($queued->state)->toBe(BoundedJob::STATE_QUEUED)
        ->and($cancelled->state)->toBe(BoundedJob::STATE_CANCELLED)
        ->and($cancelled->terminal())->toBeTrue()
        ->and(fn () => $cancelled->start($now->modify('+2 seconds')))->toThrow(DomainException::class);
});

it('rejects malformed idempotency and impossible counters', function () {
    $now = new DateTimeImmutable('2026-07-03T10:00:00+00:00');

    expect(fn () => BoundedJob::queued('data.export', 7, 10, 'not-a-hash', $now))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => BoundedJob::queued('data.export', 7, 10, hash('sha256', 'x'), $now)
            ->start($now)
            ->advance('done', 11, 11, 0, $now, $now))
        ->toThrow(InvalidArgumentException::class);
});

it('returns the active idempotent job instead of dispatching duplicate work', function () {
    $repository = new class implements JobRepository {
        /** @var array<int,BoundedJob> */
        public array $jobs = [];

        public function create(BoundedJob $job): BoundedJob
        {
            $stored = $job->withId(count($this->jobs) + 1);
            $this->jobs[$stored->id] = $stored;

            return $stored;
        }

        public function find(int $id): ?BoundedJob
        {
            return $this->jobs[$id] ?? null;
        }

        public function findActive(string $kind, string $inputHash): ?BoundedJob
        {
            foreach ($this->jobs as $job) {
                if ($job->kind === $kind && $job->inputHash === $inputHash && ! $job->terminal()) {
                    return $job;
                }
            }

            return null;
        }

        public function save(BoundedJob $job): void
        {
            $this->jobs[$job->id] = $job;
        }
    };
    $dispatcher = new class implements JobDispatcher {
        /** @var list<int> */
        public array $dispatched = [];

        public function available(): bool
        {
            return true;
        }

        public function dispatch(BoundedJob $job): void
        {
            $this->dispatched[] = $job->id;
        }

        public function cancel(int $jobId): void
        {
        }
    };
    $service = new JobService($repository, $dispatcher);
    $now     = new DateTimeImmutable('2026-07-03T10:00:00+00:00');
    $hash    = hash('sha256', 'same-operation');

    $first  = $service->enqueue('data.export', 7, 50, $hash, $now);
    $second = $service->enqueue('data.export', 7, 50, $hash, $now->modify('+1 second'));

    expect($second)->toBe($first)
        ->and($repository->jobs)->toHaveCount(1)
        ->and($dispatcher->dispatched)->toBe([1]);
});
