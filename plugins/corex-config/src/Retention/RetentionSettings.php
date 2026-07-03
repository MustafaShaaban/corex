<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

defined('ABSPATH') || exit;

/**
 * Pure rules for submission data retention (spec 065). A retention window of 0 means "keep forever"
 * (retention disabled); any positive number of days enables pruning of submissions older than that.
 * This model owns only the safe, testable decisions — the sanitised window, whether retention is
 * enabled, and the shape of the dry-run preview. It never deletes anything; the boundary does, and
 * only after an explicit preview + confirmation. WordPress-free, so it is unit-testable.
 */
final class RetentionSettings
{
    /** Upper bound on the window (10 years) — a guard against a fat-fingered huge value, not a policy. */
    public const MAX_DAYS = 3650;

    /** Never prune more than this many records in a single manual run (safety bound). */
    public const MAX_PRUNE = 500;

    /** Clamp an arbitrary input to a valid retention window in days (0 = keep forever). */
    public function sanitizeDays(mixed $value): int
    {
        $days = (int) $value;

        if ($days < 0) {
            return 0;
        }

        return min($days, self::MAX_DAYS);
    }

    public function isEnabled(int $days): bool
    {
        return $days > 0;
    }

    /**
     * The dry-run preview model: the window, whether retention is on, and how many submissions would be
     * removed right now (the count the boundary measured). Never a fabricated number.
     *
     * @return array{days:int,enabled:bool,count:int,willPrune:bool}
     */
    public function preview(int $days, int $count): array
    {
        $enabled = $this->isEnabled($days);
        $count   = max(0, $count);

        return [
            'days'      => $days,
            'enabled'   => $enabled,
            'count'     => $count,
            'willPrune' => $enabled && $count > 0,
        ];
    }
}
