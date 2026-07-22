<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

use DateTimeImmutable;

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

    /**
     * Derive the status this actor sees, from the shared record plus their own state row.
     *
     * Precedence, highest first — the collisions are the whole point, so they are fixed here rather
     * than left to each caller:
     *   1. `resolved` — the condition ended. That is a fact about the record and outranks anything
     *      one user did to their copy (FR-010).
     *   2. `expired`  — the record aged out on its own terms.
     *   3. `dismissed`— the user hid it, which supersedes their earlier read/snooze.
     *   4. `snoozed`  — only while `snoozed_until` is still in the future; an elapsed snooze is not
     *      a status, the item is simply back.
     *   5. `read` / `unread`.
     *
     * @param array{read_at?:?string,dismissed_at?:?string,snoozed_until?:?string} $userState
     */
    public static function derive(
        ?string $resolvedAt,
        ?string $expiresAt,
        array $userState,
        DateTimeImmutable $now,
    ): string {
        if (self::isSet($resolvedAt)) {
            return self::RESOLVED;
        }

        if (self::isPast($expiresAt, $now)) {
            return self::EXPIRED;
        }

        if (self::isSet($userState['dismissed_at'] ?? null)) {
            return self::DISMISSED;
        }

        if (self::isFuture($userState['snoozed_until'] ?? null, $now)) {
            return self::SNOOZED;
        }

        return self::isSet($userState['read_at'] ?? null) ? self::READ : self::UNREAD;
    }

    private static function isSet(?string $value): bool
    {
        return $value !== null && $value !== '' && $value !== '0000-00-00 00:00:00';
    }

    private static function isPast(?string $value, DateTimeImmutable $now): bool
    {
        return self::isSet($value) && new DateTimeImmutable((string) $value) <= $now;
    }

    private static function isFuture(?string $value, DateTimeImmutable $now): bool
    {
        return self::isSet($value) && new DateTimeImmutable((string) $value) > $now;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return self::ALL;
    }
}
