<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * The per-user status of a notification. Derived from the shared record plus the user's state row:
 * `dismissed` (the user hid it) is deliberately distinct from `resolved` (the condition ended),
 * so one user dismissing a shared, condition-based notification never resolves it for everyone
 * (spec 072 FR-010).
 */
final class NotificationStatus
{
    public const UNREAD     = 'unread';
    public const READ       = 'read';
    public const SNOOZED    = 'snoozed';
    public const DISMISSED  = 'dismissed';
    public const RESOLVED   = 'resolved';
    public const EXPIRED    = 'expired';

    private const ALL = [
        self::UNREAD, self::READ, self::SNOOZED, self::DISMISSED, self::RESOLVED, self::EXPIRED,
    ];

    public static function isValid(string $status): bool
    {
        return in_array($status, self::ALL, true);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return self::ALL;
    }
}
