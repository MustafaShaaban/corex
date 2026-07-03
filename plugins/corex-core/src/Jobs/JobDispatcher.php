<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Jobs;

defined('ABSPATH') || exit;

interface JobDispatcher
{
    public function available(): bool;

    public function dispatch(BoundedJob $job): void;

    public function cancel(int $jobId): void;
}
