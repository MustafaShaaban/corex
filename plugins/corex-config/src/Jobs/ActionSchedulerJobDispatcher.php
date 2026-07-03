<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Jobs;

defined('ABSPATH') || exit;

use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobDispatcher;
use RuntimeException;

final class ActionSchedulerJobDispatcher implements JobDispatcher
{
    public const HOOK  = 'corex_run_bounded_job';
    public const GROUP = 'corex-jobs';

    public function available(): bool
    {
        return function_exists('as_enqueue_async_action') && function_exists('as_unschedule_all_actions');
    }

    public function dispatch(BoundedJob $job): void
    {
        if (! $this->available()) {
            throw new RuntimeException('Action Scheduler is unavailable.');
        }

        as_enqueue_async_action(self::HOOK, [$job->id], self::GROUP);
    }

    public function cancel(int $jobId): void
    {
        if ($this->available()) {
            as_unschedule_all_actions(self::HOOK, [$jobId], self::GROUP);
        }
    }
}
