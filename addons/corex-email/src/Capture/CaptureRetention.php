<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Capture;

defined('ABSPATH') || exit;

use Corex\Email\Studio\EmailRecordPruning;
use DateTimeImmutable;

/**
 * Once a day, deletes captured and held emails kept longer than their 30 days (spec 113,
 * FR-020). They hold whole messages, addresses included, and were kept forever before.
 *
 * In batches, and for a bounded time, so a site with thousands to remove does not run out of
 * time or memory on a shared host: what is left is removed by the next run.
 */
final readonly class CaptureRetention
{
    public const HOOK = 'corex_email_capture_retention';

    private const TYPE    = 'captured_email';
    private const BATCH   = 200;
    private const SECONDS = 20;

    public function __construct(private EmailRecordPruning $store)
    {
    }

    public function register(): void
    {
        add_action(self::HOOK, [$this, 'run']);
        add_action('init', [$this, 'ensureScheduled']);
    }

    public function ensureScheduled(): void
    {
        if (wp_next_scheduled(self::HOOK) === false) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', self::HOOK);
        }
    }

    /** @return int How many emails were deleted. */
    public function run(?DateTimeImmutable $now = null): int
    {
        $cutoff = ($now ?? new DateTimeImmutable('now'))->modify('-' . CapturedEmailRepository::RETENTION_DAYS . ' days');
        $until = microtime(true) + self::SECONDS;
        $removed = 0;

        do {
            $batch = $this->store->pruneOlderThan(self::TYPE, $cutoff, self::BATCH);
            $removed += $batch;
        } while ($batch === self::BATCH && microtime(true) < $until);

        return $removed;
    }
}
