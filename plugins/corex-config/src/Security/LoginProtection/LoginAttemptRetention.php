<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use Corex\Config\Retention\PrunableStore;
use DateTimeImmutable;

/**
 * Sign-in records as one of the stores the daily retention sweep prunes (spec 124, FR-019).
 *
 * The window is the login settings' "keep sign-in records" days, read when the sweep runs, so a
 * changed setting applies to rows already stored. A lockout that has not ended is kept however old
 * it is. Rows stored before spec 124 hold plain hashes that can be read back by guessing; they are
 * counted until they leave the counting window and deleted within the retention period (30 days by
 * default). The sweep runs on each site's own cron, and the table is the site's own, so a network's
 * sites are pruned one by one.
 */
final class LoginAttemptRetention implements PrunableStore
{
    public function __construct(
        private readonly WpLoginAttemptStore $attempts,
        private readonly LoginProtectionSettingsStore $settings,
        private readonly int $batchSize = 500,
    ) {
    }

    public function key(): string
    {
        return 'login_attempts';
    }

    public function label(): string
    {
        return __('Sign-in records', 'corex');
    }

    public function retentionDays(): int
    {
        return $this->settings->current()->retainDays;
    }

    public function pruneOlderThan(DateTimeImmutable $cutoff): int
    {
        $now = new DateTimeImmutable('now');
        $removed = 0;

        do {
            $batch = $this->attempts->pruneOlderThan($cutoff, $now, $this->batchSize);
            $removed += $batch;
        } while ($batch === $this->batchSize);

        return $removed;
    }
}
