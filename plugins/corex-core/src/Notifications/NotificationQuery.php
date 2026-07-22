<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Notifications;

defined('ABSPATH') || exit;

/**
 * A bounded, validated query for a notification list. Page size is clamped so no caller can request
 * an unbounded result (spec 072 FR-026); unknown filter values are dropped rather than defaulted.
 */
final class NotificationQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param string|null $category  One of NotificationCategory, or null for any.
     * @param string|null $severity  One of NotificationSeverity, or null for any.
     * @param string|null $status    One of NotificationStatus, or null for any. This is the *derived
     *                               per-user* status (see NotificationStatus::derive) — it needs the
     *                               actor's state row, so it is applied by the actor-scoped read, not
     *                               as a WHERE clause on the shared record.
     * @param string|null $sourceModule Restrict to one source module, or null.
     * @param bool        $unreadOnly Restricts to records with no `resolved_at` — i.e. **unresolved
     *                               conditions**, which is not the same as "this actor has not read
     *                               it". Read state is per-user and lives on the state row. Use
     *                               `$status = NotificationStatus::UNREAD` when you mean the actor's
     *                               unread items; the misreading of this flag has already produced
     *                               two defects (the Attention widget and the drawer).
     */
    private function __construct(
        public readonly ?string $category,
        public readonly ?string $severity,
        public readonly ?string $status,
        public readonly ?string $sourceModule,
        public readonly bool $unreadOnly,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /** @param array<string,mixed> $filters */
    public static function fromRequest(array $filters, int $page = 1, int $perPage = 20): self
    {
        return new self(
            category: self::validEnum($filters['category'] ?? null, NotificationCategory::all()),
            severity: self::validEnum($filters['severity'] ?? null, NotificationSeverity::all()),
            status: self::validEnum($filters['status'] ?? null, NotificationStatus::all()),
            sourceModule: self::nonEmptyString($filters['source_module'] ?? null),
            unreadOnly: (bool) ($filters['unread_only'] ?? false),
            page: max(1, $page),
            perPage: min(self::MAX_PER_PAGE, max(1, $perPage)),
        );
    }

    /** @param list<string> $allowed */
    private static function validEnum(mixed $value, array $allowed): ?string
    {
        return is_string($value) && in_array($value, $allowed, true) ? $value : null;
    }

    private static function nonEmptyString(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
