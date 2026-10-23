<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobHandler;
use Corex\Media\Conversion\MediaConversionLog;
use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * The bounded WebP-regeneration job (spec 068 T200): backfills WebP siblings for existing uploads in
 * resumable batches, so a large media library is processed safely in the background rather than in
 * one blocking admin request. It owns only the {@see BoundedJob} progression + counters; the actual
 * gather/plan/convert work is the {@see MediaRegenerationSource}. Originals and existing siblings are
 * never overwritten. Pure progression logic, so it is unit-testable with a fake source.
 *
 * Spec 119 (US-SET-06): started from Settings > Media, it hands each batch's failures, and the end
 * of the run, to a {@see MediaConversionLog}, so the page can list them.
 */
final class MediaRegenerationJob implements JobHandler
{
    public const KIND = 'media-webp-regeneration';

    public function __construct(
        private readonly MediaRegenerationSource $source,
        private readonly ?MediaConversionLog $log = null,
    ) {
    }

    public function kind(): string
    {
        return self::KIND;
    }

    public function handle(BoundedJob $job, int $batchSize): BoundedJob
    {
        $limit  = max(1, $batchSize);
        $counts = $this->source->convertBatch($job->processed, $limit);

        // Never advance past the declared total, so a final partial batch still completes exactly.
        $processed = min($job->total, $job->processed + $limit);
        // Never count more outcomes than items processed: the library can shrink during a run.
        $succeeded = min($processed, $job->succeeded + max(0, (int) ($counts['succeeded'] ?? 0)));
        $failed    = min($processed - $succeeded, $job->failed + max(0, (int) ($counts['failed'] ?? 0)));

        $failures = $counts['failures'] ?? [];
        if ($failures !== []) {
            $this->log?->failed($job->id, $failures);
        }

        $advanced = $job->advance(
            (string) $processed,
            $processed,
            $succeeded,
            $failed,
            null,
            new DateTimeImmutable('now'),
        );

        if ($processed < $job->total) {
            return $advanced;
        }

        $done = $advanced->complete(self::KIND . ':' . $job->id, new DateTimeImmutable('now'));
        $this->log?->finished($done);

        return $done;
    }
}
