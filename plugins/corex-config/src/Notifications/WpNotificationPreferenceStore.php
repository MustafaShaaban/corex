<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications;

defined('ABSPATH') || exit;

use Corex\Notifications\NotificationPreference;
use Corex\Notifications\NotificationPreferenceStore;

/**
 * User-meta adapter for notification preferences (spec 072 FR-020). Per-user, low-volume, WordPress-
 * native data — user meta, not a custom table (the reinvent-the-platform rule). Only muted categories
 * are stored; everything else defaults to shown, and mandatory categories are enforced by the value
 * object, never by what happens to be persisted.
 */
final class WpNotificationPreferenceStore implements NotificationPreferenceStore
{
    private const META_KEY = 'corex_notification_preferences';

    public function forUser(int $userId): NotificationPreference
    {
        if ($userId < 1) {
            return NotificationPreference::defaults();
        }

        $stored = get_user_meta($userId, self::META_KEY, true);

        return is_array($stored) ? NotificationPreference::fromMap($stored) : NotificationPreference::defaults();
    }

    public function save(int $userId, NotificationPreference $preference): void
    {
        if ($userId < 1) {
            return;
        }

        update_user_meta($userId, self::META_KEY, $preference->toArray());
    }
}
