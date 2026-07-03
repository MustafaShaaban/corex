<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Jobs;

defined('ABSPATH') || exit;

interface JobHandler
{
    public function kind(): string;

    public function handle(BoundedJob $job, int $batchSize): BoundedJob;
}
