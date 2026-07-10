<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * The batch source the {@see MediaRegenerationJob} draws from: the count of image attachments to
 * process, and a single bounded batch of WebP conversions (gather → plan → convert) starting at an
 * offset. Conversions never overwrite an existing sibling or the original (spec 061). Splitting
 * this out keeps the job's progression logic pure and unit-testable; the WordPress-backed
 * implementation performs the real attachment query and file conversions.
 */
interface MediaRegenerationSource
{
    public function total(): int;

    /**
     * Convert one bounded batch and report how many succeeded and failed. Skipped attachments
     * (already have a sibling, or unsupported) count as neither — they are not failures.
     *
     * @return array{succeeded:int,failed:int}
     */
    public function convertBatch(int $offset, int $limit): array;
}
