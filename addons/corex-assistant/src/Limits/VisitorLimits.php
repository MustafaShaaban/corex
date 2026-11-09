<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Limits;

defined('ABSPATH') || exit;

/**
 * How many questions a visitor, and the whole site, may ask (spec 129, User Story 5); the owner sets
 * each on Settings → Assistant → Limits (FR-024).
 *
 * Counts live in transients that expire with their window. Every answer costs a model call, so the
 * caps are what keep one visitor from spending a site's free tiers.
 */
final class VisitorLimits
{
    private const PREFIX = 'corex_ai_';

    /** @var array{hour: int, day: int, site: int}|null */
    private ?array $read = null;

    /**
     * @param \Closure(string): mixed                               $get
     * @param \Closure(string, int, int): void                      $set  key, value, seconds to keep
     * @param \Closure(): int                                       $now
     * @param \Closure(): array{hour: int, day: int, site: int}     $caps a visitor's an hour and a day, and the
     *                                                                     site's a day, read when first needed
     */
    public function __construct(
        private readonly \Closure $get,
        private readonly \Closure $set,
        private readonly \Closure $now,
        private readonly \Closure $caps,
    ) {
    }

    /** Counts the question when it is allowed; a refused one is not counted. */
    public function allow(string $visitor): bool
    {
        $now = ($this->now)();
        $hour = intdiv($now, 3600);
        $day = intdiv($now, 86400);

        $counters = [
            [self::PREFIX . 'vh_' . $visitor . '_' . $hour, $this->cap('hour'), 3600],
            [self::PREFIX . 'vd_' . $visitor . '_' . $day, $this->cap('day'), 86400],
            [$this->siteKey($day), $this->cap('site'), 86400],
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
        return $this->cap('site');
    }

    private function cap(string $name): int
    {
        $this->read ??= ($this->caps)();

        return max(0, (int) ($this->read[$name] ?? 0));
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
