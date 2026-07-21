<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * The closed severity vocabulary for a notification, ordered so a per-user severity floor can be
 * compared. `critical` is the most severe; `success` the least. Routine successes generally belong
 * as toasts or Activity, not as permanent notifications (spec 072 FR-007).
 */
final class NotificationSeverity
{
    public const CRITICAL    = 'critical';
    public const ERROR       = 'error';
    public const WARNING     = 'warning';
    public const ACTION      = 'action';
    public const INFORMATION = 'information';
    public const SUCCESS     = 'success';

    /** Highest rank = most severe. Used for the preference severity floor. */
    private const RANKS = [
        self::CRITICAL    => 60,
        self::ERROR       => 50,
        self::WARNING     => 40,
        self::ACTION      => 30,
        self::INFORMATION => 20,
        self::SUCCESS     => 10,
    ];

    public static function isValid(string $severity): bool
    {
        return isset(self::RANKS[$severity]);
    }

    public static function rank(string $severity): int
    {
        return self::RANKS[$severity] ?? 0;
    }

    /** @return list<string> */
    public static function all(): array
    {
        return array_keys(self::RANKS);
    }
}
