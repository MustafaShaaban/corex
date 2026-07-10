<?php

/**
 * Unit tests for the bounded WebP-regeneration job (spec 068: T199). No WordPress, no file I/O —
 * a fake source stands in for the attachment query + conversions. Contract: the job processes the
 * library in bounded, resumable batches, accumulates succeeded/failed counters, never advances past
 * the total, and completes exactly once every attachment is processed.
 *
 * @package Corex\Tests\Unit\Media
 */

declare(strict_types=1);

use Corex\Jobs\BoundedJob;
use Corex\Media\MediaRegenerationJob;
use Corex\Media\MediaRegenerationSource;

/**
 * A fake source recording each batch request and returning fixed per-batch counts.
 */
function fakeMediaSource(int $total, array $batches): MediaRegenerationSource
{
    return new class ($total, $batches) implements MediaRegenerationSource {
        public array $calls = [];

        public function __construct(
            private readonly int $totalItems,
            private readonly array $batches,
        ) {
        }

        public function total(): int
        {
            return $this->totalItems;
        }

        public function convertBatch(int $offset, int $limit): array
        {
            $this->calls[] = ['offset' => $offset, 'limit' => $limit];

            return $this->batches[count($this->calls) - 1] ?? ['succeeded' => 0, 'failed' => 0];
        }
    };
}

function runningMediaJob(int $total): BoundedJob
{
    // A clearly-past creation time so the real `now` used inside handle() is always later.
    $now = new DateTimeImmutable('2020-01-01T00:00:00+00:00');

    return BoundedJob::queued(
        kind: MediaRegenerationJob::KIND,
        actorId: 1,
        total: $total,
        inputHash: hash('sha256', 'media-regen'),
        createdAt: $now,
    )->withId(21)->start($now);
}

it('processes the library in bounded batches and completes exactly at the total', function () {
    $source = fakeMediaSource(5, [
        ['succeeded' => 2, 'failed' => 0],
        ['succeeded' => 1, 'failed' => 1],
        ['succeeded' => 1, 'failed' => 0],
    ]);
    $job = new MediaRegenerationJob($source);

    $first = $job->handle( runningMediaJob( 5 ), 2 );
    expect($first->processed)->toBe(2)
        ->and($first->state)->toBe(BoundedJob::STATE_RUNNING);

    $second = $job->handle($first, 2);
    expect($second->processed)->toBe(4)
        ->and($second->succeeded)->toBe(3)
        ->and($second->failed)->toBe(1);

    $third = $job->handle($second, 2);
    // The final batch is clamped to the total (4 → 5, not 6) and the job completes.
    expect($third->processed)->toBe(5)
        ->and($third->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($third->succeeded)->toBe(4)
        ->and($third->failed)->toBe(1);

    // Each batch was requested at the correct offset.
    expect($source->calls)->toBe([
        ['offset' => 0, 'limit' => 2],
        ['offset' => 2, 'limit' => 2],
        ['offset' => 4, 'limit' => 2],
    ]);
});

it('clamps a negative or zero batch size to at least one item', function () {
    $source = fakeMediaSource(1, [['succeeded' => 1, 'failed' => 0]]);
    $done   = (new MediaRegenerationJob($source))->handle(runningMediaJob(1), 0);

    expect($done->processed)->toBe(1)
        ->and($done->state)->toBe(BoundedJob::STATE_COMPLETED)
        ->and($source->calls[0]['limit'])->toBe(1);
});
