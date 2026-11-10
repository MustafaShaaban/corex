<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Booking;

defined('ABSPATH') || exit;

/**
 * The claims as rows of the options table, one per time: `corex_assistant_slot_<seconds>`, holding `0` while
 * pending and then the submission's id (spec 129, FR-021).
 *
 * Claimed by the database, as {@see \Corex\Assistant\Limits\VisitorLock} is: `INSERT IGNORE` on the table's
 * unique option name, which one of two racing requests wins. `add_option()` would not do: it writes with
 * `INSERT ... ON DUPLICATE KEY UPDATE`, so both racers would be told they added the row. The rows are never
 * autoloaded and never read through WordPress's option functions, so the options cache is not involved.
 */
final class WpSlotClaims implements SlotClaims
{
    private const PREFIX = 'corex_assistant_slot_';

    /** A claim whose request is still being taken. */
    private const PENDING = '0';

    public function __construct(private readonly \wpdb $db)
    {
    }

    public function claimed(): array
    {
        return array_keys($this->rows());
    }

    public function claim(int $slot): bool
    {
        return $this->db->query($this->db->prepare(
            "INSERT IGNORE INTO {$this->db->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
            self::PREFIX . $slot,
            self::PENDING,
        )) === 1;
    }

    public function confirm(int $slot, int $submissionId): void
    {
        $this->db->update(
            $this->db->options,
            ['option_value' => (string) $submissionId],
            ['option_name' => self::PREFIX . $slot, 'option_value' => self::PENDING],
        );
    }

    public function release(int $slot): void
    {
        $this->db->delete($this->db->options, ['option_name' => self::PREFIX . $slot, 'option_value' => self::PENDING]);
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
