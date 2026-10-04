<?php

/**
 * Leaves the notification tables as a test found them.
 *
 * The integration suite runs against a developer's real install, whose notification table holds
 * the developer's own notifications and what each user has read. Three test files used to start
 * every test with an unqualified `DELETE FROM` on both tables; every run emptied them. A test
 * cleans up by naming its rows instead: a dedup key carries a prefix no producer writes
 * (`repository.test:`), or is the key of a flow only the test creates. A test that runs a real
 * producer, whose keys are the product's own, snapshots those rows and restores them.
 *
 * @package Corex\Tests\Support
 */

declare(strict_types=1);

namespace Corex\Tests\Support;

use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Notifications\NotificationUserStateTable;
use Corex\Database\Schema\Migrator;

final class NotificationRows
{
    /** Delete the notification with exactly this dedup key, and every user's state for it. */
    public static function forget(string $dedupKey): void
    {
        self::forgetWhere('dedup_key = %s', $dedupKey);
    }

    /** Delete every notification whose dedup key starts with `$prefix`, and every user's state for them. */
    public static function forgetPrefixed(string $prefix): void
    {
        self::forgetWhere('dedup_key LIKE %s', self::startingWith($prefix));
    }

    public static function countPrefixed(string $prefix): int
    {
        global $wpdb;

        return (int) $wpdb->get_var($wpdb->prepare(
            'SELECT COUNT(*) FROM ' . self::table(NotificationTable::NAME) . ' WHERE dedup_key LIKE %s',
            self::startingWith($prefix),
        ));
    }

    /**
     * The rows whose dedup key starts with `$prefix`, exactly as stored.
     *
     * For a test that runs a real producer. What a producer writes is keyed the way the product
     * keys it, so the rows may be the developer's own, already there: they cannot be deleted
     * afterwards, only handed back as they were ({@see restorePrefixed()}).
     *
     * @return list<array<string,mixed>>
     */
    public static function snapshotPrefixed(string $prefix): array
    {
        global $wpdb;

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT * FROM ' . self::table(NotificationTable::NAME) . ' WHERE dedup_key LIKE %s',
            self::startingWith($prefix),
        ), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * Put a snapshot back: a row that was there reads as it did, a row that was not is gone.
     *
     * @param list<array<string,mixed>> $rows
     */
    public static function restorePrefixed(string $prefix, array $rows): void
    {
        global $wpdb;

        $notifications = self::table(NotificationTable::NAME);
        $before        = array_map(static fn (array $row): int => (int) $row['id'], $rows);

        foreach (self::snapshotPrefixed($prefix) as $row) {
            if (! in_array((int) $row['id'], $before, true)) {
                $wpdb->delete(self::table(NotificationUserStateTable::NAME), ['notification_id' => (int) $row['id']]);
                $wpdb->delete($notifications, ['id' => (int) $row['id']]);
            }
        }

        foreach ($rows as $row) {
            $wpdb->update($notifications, $row, ['id' => (int) $row['id']]);
        }
    }

    private static function forgetWhere(string $condition, string $value): void
    {
        global $wpdb;

        $notifications = self::table(NotificationTable::NAME);

        // State first: it is keyed by notification id, so nothing could find it once the
        // notifications it belongs to are gone.
        $wpdb->query($wpdb->prepare(
            'DELETE FROM ' . self::table(NotificationUserStateTable::NAME)
            . ' WHERE notification_id IN (SELECT id FROM ' . $notifications . ' WHERE ' . $condition . ')',
            $value,
        ));
        $wpdb->query($wpdb->prepare('DELETE FROM ' . $notifications . ' WHERE ' . $condition, $value));
    }

    private static function startingWith(string $prefix): string
    {
        global $wpdb;

        return $wpdb->esc_like($prefix) . '%';
    }

    private static function table(string $name): string
    {
        return (new Migrator())->fullName($name);
    }
}
