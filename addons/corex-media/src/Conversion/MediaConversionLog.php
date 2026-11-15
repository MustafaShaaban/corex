<?php

/**
 * @package Corex\Media
 */

declare(strict_types=1);

namespace Corex\Media\Conversion;

defined('ABSPATH') || exit;

use Corex\Jobs\BoundedJob;

/**
 * Where a library conversion keeps what the Settings page shows about it (spec 119, US-SET-06):
 * the files that failed with their reasons, and the end of the run.
 */
interface MediaConversionLog
{
    /**
     * @param list<array{file:string,reason:string}> $failures
     */
    public function failed(int $jobId, array $failures): void;

    /** A run reached its end. */
    public function finished(BoundedJob $job): void;
}
