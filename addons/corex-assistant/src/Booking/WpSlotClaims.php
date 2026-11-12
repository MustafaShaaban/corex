<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

/**
 * The claims as rows of the options table, one per time: `corex_assistant_slot_<seconds>`, holding
 * `pending:<when>` while the request is being taken and then the submission's id (spec 129, FR-021).
 *
 * Claimed by the database, as {@see \Corex\Assistant\Limits\VisitorLock} is: `INSERT IGNORE` on the table's
 * unique option name, which one of two racing requests wins. `add_option()` would not do: it writes with
 * `INSERT ... ON DUPLICATE KEY UPDATE`, so both racers would be told they added the row. A pending claim older
 * than {@see STALE_SECONDS} belongs to a request that died before it was stored: it is free, and the next
 * claim takes it over with an `UPDATE` that names the old value, which one request wins too. The rows are
 * never autoloaded and never read through WordPress's option functions, so the options cache is not involved.
 */
final class WpSlotClaims implements SlotClaims
{
    /** Longer than any request is let run. */
    public const STALE_SECONDS = 900;

    private const PREFIX = 'corex_assistant_slot_';

    private const PENDING = 'pending:';

    /** @var array<int, string> time => the pending value this request wrote */
    private array $pending = [];

    /**
     * @param \Closure(): int $now
     */
    public function __construct(
        private readonly \wpdb $db,
        private readonly \Closure $now,
    ) {
    }

    public function claimed(): array
    {
        return array_keys(array_filter($this->rows(), fn (string $held): bool => ! $this->isStale($held)));
    }

    public function claim(int $slot): bool
    {
        $value = self::PENDING . ($this->now)();
        $taken = $this->db->query($this->db->prepare(
            "INSERT IGNORE INTO {$this->db->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
            self::PREFIX . $slot,
            $value,
        )) === 1;
        if (! $taken) {
            $held = $this->rows()[$slot] ?? null;
            $taken = $held !== null && $this->isStale($held) && $this->db->update(
                $this->db->options,
                ['option_value' => $value],
                ['option_name' => self::PREFIX . $slot, 'option_value' => $held],
            ) === 1;
        }
        if ($taken) {
            $this->pending[$slot] = $value;
        }

        return $taken;
    }

    public function confirm(int $slot, int $submissionId): void
    {
        if (isset($this->pending[$slot])) {
            $this->db->update(
                $this->db->options,
                ['option_value' => (string) $submissionId],
                ['option_name' => self::PREFIX . $slot, 'option_value' => $this->pending[$slot]],
            );
            unset($this->pending[$slot]);
        }
    }

    public function release(int $slot): void
    {
        if (isset($this->pending[$slot])) {
            $this->db->delete($this->db->options, ['option_name' => self::PREFIX . $slot, 'option_value' => $this->pending[$slot]]);
            unset($this->pending[$slot]);
        }
    }

    public function releaseFor(int $submissionId): void
    {
        if ($submissionId <= 0) {
            return;
        }
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->db->options} WHERE option_name LIKE %s AND option_value = %s",
            $this->db->esc_like(self::PREFIX) . '%',
            (string) $submissionId,
        ));
    }

    public function forgetBefore(int $moment): void
    {
        $past = array_keys(array_filter(
            $this->rows(),
            static fn (int $slot): bool => $slot < $moment,
            ARRAY_FILTER_USE_KEY,
        ));
        if ($past === []) {
            return;
        }
        $names = array_map(static fn (int $slot): string => self::PREFIX . $slot, $past);
        $this->db->query($this->db->prepare(
            "DELETE FROM {$this->db->options} WHERE option_name IN (" . implode(', ', array_fill(0, count($names), '%s')) . ')',
            ...$names,
        ));
    }

    /** A pending claim whose request has had longer than any request runs. */
    private function isStale(string $held): bool
    {
        return str_starts_with($held, self::PENDING)
            && ($this->now)() - (int) substr($held, strlen(self::PENDING)) > self::STALE_SECONDS;
    }

    /** @return array<int, string> time => what the row holds */
    private function rows(): array
    {
        $rows = $this->db->get_results($this->db->prepare(
            "SELECT option_name, option_value FROM {$this->db->options} WHERE option_name LIKE %s",
            $this->db->esc_like(self::PREFIX) . '%',
        ), ARRAY_A);
        $claims = [];
        foreach (is_array($rows) ? $rows : [] as $row) {
            $slot = substr((string) $row['option_name'], strlen(self::PREFIX));
            if (ctype_digit($slot)) {
                $claims[(int) $slot] = (string) $row['option_value'];
            }
        }
        ksort($claims);

        return $claims;
    }
}
