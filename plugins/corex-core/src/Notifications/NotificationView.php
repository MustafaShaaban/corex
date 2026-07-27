<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * Which of the three notification views an item belongs to right now (spec 074, FR-4.1/FR-4.3).
 *
 * The distinction this exists to make: **read is not resolved**. The old screen filtered "Requires
 * attention" on the actor's unread state, so reading a production-readiness blocker removed it from
 * the attention list while the blocker was still true. Whether something still asks anything of you
 * is a fact about the condition and about what you did with it — never about whether you have
 * looked at it.
 *
 * Snoozing is the one thing that does move an item out of Action needed without resolving it, and
 * it moves it to Updates rather than History: you said "not now", not "done". When the snooze
 * elapses {@see NotificationStatus::derive()} returns read/unread again and the item comes back on
 * its own.
 */
final class NotificationView
{
    /** Live, and it asks something of you. */
    public const ACTION_NEEDED = 'action_needed';

    /** Live, but it asks nothing of you — or you snoozed it. */
    public const UPDATES = 'updates';

    /** Over: resolved, dismissed, or expired. */
    public const HISTORY = 'history';

    /** @var list<string> */
    private const ALL = [self::ACTION_NEEDED, self::UPDATES, self::HISTORY];

    /** Statuses that mean the condition is over, whatever its severity. */
    private const CLOSED = [
        NotificationStatus::RESOLVED,
        NotificationStatus::DISMISSED,
        NotificationStatus::EXPIRED,
    ];

    /**
     * Severities that ask something of the person seeing them. `information` and `success` report;
     * the rest want a decision, which is why reading one cannot be what clears it.
     *
     * @var list<string>
     */
    private const DEMANDING = [
        NotificationSeverity::CRITICAL,
        NotificationSeverity::ERROR,
        NotificationSeverity::WARNING,
        NotificationSeverity::ACTION,
    ];

    public static function isValid(string $view): bool
    {
        return in_array($view, self::ALL, true);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return self::ALL;
    }

    /**
     * @param string $status    The derived per-user status ({@see NotificationStatus::derive()}).
     * @param string $severity  The record's severity.
     * @param bool   $hasAction Whether the record carries a primary action.
     */
    public static function of(string $status, string $severity, bool $hasAction): string
    {
        if (in_array($status, self::CLOSED, true)) {
            return self::HISTORY;
        }

        if ($status === NotificationStatus::SNOOZED) {
            return self::UPDATES;
        }

        return in_array($severity, self::DEMANDING, true) || $hasAction
            ? self::ACTION_NEEDED
            : self::UPDATES;
    }

    /**
     * Whether this item still needs somebody to do something — read or not.
     */
    public static function needsAction(string $status, string $severity, bool $hasAction): bool
    {
        return self::of($status, $severity, $hasAction) === self::ACTION_NEEDED;
    }
}
