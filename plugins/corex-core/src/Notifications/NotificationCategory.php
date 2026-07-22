<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * The closed category vocabulary. Extensible by adding a const here (a typed identifier, not a
 * UI-only list) — kept closed so a mistyped category is rejected rather than silently created.
 */
final class NotificationCategory
{
    public const SUBMISSIONS     = 'submissions';
    public const EMAIL           = 'email';
    public const JOBS            = 'jobs';
    public const SECURITY        = 'security';
    public const ACCESS          = 'access';
    public const OPERATIONS      = 'operations';
    public const READINESS       = 'readiness';
    public const IMPORTS_EXPORTS = 'imports_exports';
    public const EDITORIAL       = 'editorial';
    public const SETUP           = 'setup';
    public const SYSTEM          = 'system';

    private const ALL = [
        self::SUBMISSIONS, self::EMAIL, self::JOBS, self::SECURITY, self::ACCESS, self::OPERATIONS,
        self::READINESS, self::IMPORTS_EXPORTS, self::EDITORIAL, self::SETUP, self::SYSTEM,
    ];

    public static function isValid(string $category): bool
    {
        return in_array($category, self::ALL, true);
    }

    /** @return list<string> */
    public static function all(): array
    {
        return self::ALL;
    }
}
