<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * The framework's first recurring job (spec 072 FR-022): a daily WP-Cron event that runs the shared
 * {@see RetentionSweep} so managed stores (notifications today, more later) are pruned to their
 * windows without anyone visiting a screen. Idempotent — it schedules the event once and runs the
 * sweep best-effort; the sweep itself skips keep-forever stores and prunes in bounded batches.
 */
final class RetentionScheduler
{
    public const HOOK = 'corex_retention_sweep';

    public function __construct(private readonly RetentionSweep $sweep)
    {
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [$this, 'ensureScheduled']);
    }

    /** Schedule the daily sweep once; a no-op after it is first booked. */
    public function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::HOOK) === false) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    public function run(): void
    {
        $this->sweep->apply(new DateTimeImmutable('now'));
    }
}
