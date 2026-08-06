<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Jobs;

defined('ABSPATH') || exit;

use Corex\Jobs\BoundedJob;
use Corex\Jobs\JobDispatcher;
use Corex\Multisite\SiteScope;
use RuntimeException;

final class CronJobDispatcher implements JobDispatcher
{
    public const HOOK = ActionSchedulerJobDispatcher::HOOK;

    public function __construct(private readonly SiteScope $siteScope)
    {
    }

    public function available(): bool
    {
        return function_exists('wp_schedule_single_event') && function_exists('wp_clear_scheduled_hook');
    }

    public function dispatch(BoundedJob $job): void
    {
        if (! $this->available()) {
            throw new RuntimeException('WP-Cron is unavailable.');
        }

        $payload = [$job->id, $this->siteScope->currentSiteId()];

        if (wp_next_scheduled(self::HOOK, $payload) !== false) {
            return;
        }

        $timestamp = max(time() + 1, $job->nextRunAt?->getTimestamp() ?? time() + 1);
        if (! wp_schedule_single_event($timestamp, self::HOOK, $payload)) {
            throw new RuntimeException('CoreX could not schedule the bounded job.');
        }
    }

    public function cancel(int $jobId): void
    {
        if ($this->available()) {
            wp_clear_scheduled_hook(self::HOOK, [$jobId, $this->siteScope->currentSiteId()]);
        }
    }
}
