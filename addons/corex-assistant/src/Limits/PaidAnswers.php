<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Limits;

defined('ABSPATH') || exit;

/**
 * How many answers a paid service gave today, against the paid cap (spec 129, FR-017).
 *
 * A paid answer is counted once it is given, so a busy day cannot run up a bill past the cap by
 * more than the questions already in flight. The count lives in a transient for the UTC day.
 */
final class PaidAnswers
{
    private const PREFIX = 'corex_ai_paid_';

    /**
     * @param \Closure(string): mixed          $get
     * @param \Closure(string, int, int): void $set key, value, seconds to keep
     * @param \Closure(): int                  $now
     * @param \Closure(): int                  $cap the paid answers a day, read when it is needed
     */
    public function __construct(
        private readonly \Closure $get,
        private readonly \Closure $set,
        private readonly \Closure $now,
        private readonly \Closure $cap,
    ) {
    }

    /** Whether a paid service may still answer today. */
    public function left(): bool
    {
        return $this->today() < $this->cap();
    }

    /** Counts an answer a paid service gave. */
    public function record(): void
    {
        ($this->set)($this->key(), $this->today() + 1, 86400);
    }

    public function today(): int
    {
        return (int) ($this->get)($this->key());
    }

    public function cap(): int
    {
        return max(0, ($this->cap)());
    }

    private function key(): string
    {
        return self::PREFIX . intdiv(($this->now)(), 86400);
    }
}
