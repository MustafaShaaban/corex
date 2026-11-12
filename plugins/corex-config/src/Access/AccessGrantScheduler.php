<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Once a day (spec 112, FR-006, FR-008, FR-010): ends the grants whose expiry has passed, cancels
 * the pending requests for what a request may no longer ask for, and stores the refusal counts the
 * object cache holds. A grant can therefore outlive its expiry by up to a day, as the spec's
 * assumption allows. Scheduled the way the retention sweep is.
 */
final class AccessGrantScheduler
{
    public const HOOK = 'corex_access_grants_daily';

    public function __construct(
        private readonly AccessGrantService $grants,
        private readonly AccessAuditLog $audit,
    ) {
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

    public function run(): void
    {
        $now = new DateTimeImmutable('now');

        $this->grants->endExpired($now);
        $this->grants->cancelNotRequestable($now);
        $this->audit->settle();
    }
}
