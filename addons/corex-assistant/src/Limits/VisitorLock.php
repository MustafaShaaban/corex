<?php

/**
 * @package Corex\Assistant
 */

declare(strict_types=1);

namespace Corex\Assistant\Limits;

defined('ABSPATH') || exit;

/**
 * One question at a time per visitor (spec 129, User Story 5): a burst cannot slip past the counts,
 * nor hold the host's workers.
 *
 * The lock is a row of the options table, claimed by the database and not by WordPress's option
 * functions: `add_option()` writes with `INSERT ... ON DUPLICATE KEY UPDATE`, so two requests racing
 * past its cache check would both be told they added the row. Here the row is claimed with `INSERT
 * IGNORE`, which one request wins, and a lock older than {@see STALE_SECONDS} (its request died) is
 * taken over with an `UPDATE` that names the old time, which one request wins too. A request releases
 * only the lock it holds, by its time, so one that ran past {@see STALE_SECONDS} cannot release the lock
 * a later request took over. The options cache is never involved.
 */
final class VisitorLock
{
    /** The router's 22-second budget and some. */
    public const STALE_SECONDS = 45;

    private const PREFIX = 'corex_ai_lock_';

    /** @var array<string, int> visitor => the time this request claimed their lock at */
    private array $held = [];

    /**
     * @param \Closure(string, int): bool      $claim    adds the row; false when it already exists
     * @param \Closure(string): int            $read     the time stored in the row, 0 when there is none
     * @param \Closure(string, int, int): bool $takeOver replaces the time, only if it is still the one read
     * @param \Closure(string, int): void      $delete   removes the row, only if it still holds that time
     * @param \Closure(): int                  $now
     */
    public function __construct(
        private readonly \Closure $claim,
        private readonly \Closure $read,
        private readonly \Closure $takeOver,
        private readonly \Closure $delete,
        private readonly \Closure $now,
    ) {
    }

    public static function fromWordPress(): self
    {
        return new self(
            static function (string $name, int $at): bool {
                global $wpdb;

                return $wpdb->query($wpdb->prepare(
                    "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
                    $name,
                    (string) $at,
                )) === 1;
            },
            static function (string $name): int {
                global $wpdb;

                return (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
                    $name,
                ));
            },
            static function (string $name, int $was, int $at): bool {
                global $wpdb;

                return $wpdb->query($wpdb->prepare(
                    "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                    (string) $at,
                    $name,
                    (string) $was,
                )) === 1;
            },
            static function (string $name, int $at): void {
                global $wpdb;
                $wpdb->delete($wpdb->options, ['option_name' => $name, 'option_value' => (string) $at]);
            },
            static fn (): int => time(),
        );
    }

    /** Takes the visitor's lock; false while another of their questions is being answered. */
    public function take(string $visitor): bool
    {
        $name = self::PREFIX . $visitor;
        $now = ($this->now)();
        $taken = ($this->claim)($name, $now);
        if (! $taken) {
            $held = ($this->read)($name);
            // Released between the two reads: claim it again, once. Else take over only a stale lock.
            $taken = $held === 0
                ? ($this->claim)($name, $now)
                : $now - $held > self::STALE_SECONDS && ($this->takeOver)($name, $held, $now);
        }
        if ($taken) {
            $this->held[$visitor] = $now;
        }

        return $taken;
    }

    public function release(string $visitor): void
    {
        if (isset($this->held[$visitor])) {
            ($this->delete)(self::PREFIX . $visitor, $this->held[$visitor]);
            unset($this->held[$visitor]);
        }
    }
}
