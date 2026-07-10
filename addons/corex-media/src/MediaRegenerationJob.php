<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobHandler;
use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * The bounded WebP-regeneration job (spec 068 T200): backfills WebP siblings for existing uploads in
 * resumable batches, so a large media library is processed safely in the background rather than in
 * one blocking admin request. It owns only the {@see BoundedJob} progression + counters; the actual
 * gather/plan/convert work is the {@see MediaRegenerationSource}. Originals and existing siblings are
 * never overwritten. Pure progression logic, so it is unit-testable with a fake source.
 */
final class MediaRegenerationJob implements JobHandler
{
    public const KIND = 'media-webp-regeneration';

    public function __construct(private readonly MediaRegenerationSource $source)
    {
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

        $advanced = $job->advance(
            (string) $processed,
            $processed,
            $job->succeeded + max(0, (int) ($counts['succeeded'] ?? 0)),
            $job->failed + max(0, (int) ($counts['failed'] ?? 0)),
            null,
            new DateTimeImmutable('now'),
        );

        if ($processed < $job->total) {
            return $advanced;
        }

        return $advanced->complete(self::KIND . ':' . $job->id, new DateTimeImmutable('now'));
    }
}
