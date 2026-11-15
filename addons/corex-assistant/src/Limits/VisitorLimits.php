<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Limits;

defined('ABSPATH') || exit;

use Corex\Assistant\AssistantSettings;

/**
 * How many questions a visitor and the whole site may ask, and how many answers paid services may give
 * (spec 129, User Story 5). The caps are the owner's, from the Assistant screen.
 *
 * Counts live in transients that expire with their window. Every answer costs a model call, so the caps
 * are what keep one visitor from spending a site's free tiers, and a busy day from running up a bill.
 */
final class VisitorLimits
{
    private const PREFIX = 'corex_ai_';

    /** @var array{hour: int, day: int, site: int, paid: int}|null */
    private ?array $read = null;

    /**
     * @param \Closure(string): mixed                               $get
     * @param \Closure(string, int, int): void                      $set  key, value, seconds to keep
     * @param \Closure(): int                                       $now
     * @param \Closure(): array{hour: int, day: int, site: int, paid: int} $caps read when first needed
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

    /** Today's questions across the site, for the owner's screen. */
    public function siteToday(): int
    {
        return $this->count($this->siteKey(intdiv(($this->now)(), 86400)));
    }

    public function sitePerDay(): int
    {
        return $this->cap('site');
    }

    /** Whether a paid service may still answer today. */
    public function paidLeft(): bool
    {
        return $this->paidToday() < $this->cap('paid');
    }

    public function recordPaid(): void
    {
        ($this->set)($this->paidKey(), $this->paidToday() + 1, 86400);
    }

    public function paidToday(): int
    {
        return $this->count($this->paidKey());
    }

    private function paidKey(): string
    {
        return self::PREFIX . 'paid_' . intdiv(($this->now)(), 86400);
    }

    private function cap(string $name): int
    {
        $this->read ??= ($this->caps)();

        return max(0, (int) ($this->read[$name] ?? AssistantSettings::DEFAULT_LIMITS[$name]));
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
