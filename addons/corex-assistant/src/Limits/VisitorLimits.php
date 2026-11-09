<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Limits;

defined('ABSPATH') || exit;

/**
 * How many questions a visitor, and the whole site, may ask (spec 129, User Story 5).
 *
 * Counts live in transients that expire with their window. Every answer costs a model call, so the
 * caps are what keep one visitor from spending a site's free tiers.
 */
final class VisitorLimits
{
    public const PER_HOUR = 20;
    public const PER_DAY = 60;

    private const PREFIX = 'corex_ai_';

    /**
     * @param \Closure(string): mixed          $get
     * @param \Closure(string, int, int): void $set   key, value, seconds to keep
     * @param \Closure(): int                  $now
     * @param \Closure(): int                  $sitePerDay the site-wide cap, read when it is needed
     */
    public function __construct(
        private readonly \Closure $get,
        private readonly \Closure $set,
        private readonly \Closure $now,
        private readonly \Closure $sitePerDay,
    ) {
    }

    /** Counts the question when it is allowed; a refused one is not counted. */
    public function allow(string $visitor): bool
    {
        $now = ($this->now)();
        $hour = intdiv($now, 3600);
        $day = intdiv($now, 86400);

        $counters = [
            [self::PREFIX . 'vh_' . $visitor . '_' . $hour, self::PER_HOUR, 3600],
            [self::PREFIX . 'vd_' . $visitor . '_' . $day, self::PER_DAY, 86400],
            [$this->siteKey($day), $this->sitePerDay(), 86400],
        ];

        foreach ($counters as [$key, $limit]) {
            if ($this->count($key) >= $limit) {
                return false;
            }
        }

        foreach ($counters as [$key, , $seconds]) {
            ($this->set)($key, $this->count($key) + 1, $seconds);
        }

        return true;
    }

    /** Today's questions across the site, for the Settings screen. */
    public function siteToday(): int
    {
        return $this->count($this->siteKey(intdiv(($this->now)(), 86400)));
    }

    public function sitePerDay(): int
    {
        return max(0, ($this->sitePerDay)());
    }

    private function count(string $key): int
    {
        return (int) ($this->get)($key);
    }

    private function siteKey(int $day): string
    {
        return self::PREFIX . 'site_' . $day;
    }
}
