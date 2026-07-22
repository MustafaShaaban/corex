<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * Per-user notification preference persistence (spec 072 FR-020). Preferences are small, per-user, and
 * WordPress-native, so the adapter stores them in user meta rather than a custom table.
 */
interface NotificationPreferenceStore
{
    public function forUser(int $userId): NotificationPreference;

    public function save(int $userId, NotificationPreference $preference): void;
}
