<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Normalized filters for one permission-scoped Inbox page.
 */
final readonly class SubmissionInboxQuery
{
    public const STATUSES = ['new', 'in_progress', 'replied', 'closed', 'spam', 'archived'];

    private const MAX_PAGE_SIZE = 100;

    private function __construct(
        public string $search,
        public int $flowId,
        public string $status,
        public string $owner,
        public string $dateFrom,
        public string $dateTo,
        public bool $includeTest,
        public int $page,
        public int $perPage,
    ) {
    }

    /** @param array<string,mixed> $input */
    public static function from(array $input): self
    {
        $status = trim((string) ($input['status'] ?? ''));
        if ($status !== '' && ! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('The submission status filter is invalid.');
        }

        $owner = trim((string) ($input['owner'] ?? ''));
        if ($owner !== '' && preg_match('/^(user|team|role):[a-z0-9_-]+$/', $owner) !== 1) {
            throw new InvalidArgumentException('The submission owner filter is invalid.');
        }

        $dateFrom = self::date((string) ($input['date_from'] ?? ''));
        $dateTo   = self::date((string) ($input['date_to'] ?? ''));
        if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
            throw new InvalidArgumentException('The submission date range is invalid.');
        }

        return new self(
            search: trim((string) ($input['search'] ?? '')),
            flowId: max(0, (int) ($input['flow'] ?? 0)),
            status: $status,
            owner: $owner,
            dateFrom: $dateFrom,
            dateTo: $dateTo,
            includeTest: filter_var($input['include_test'] ?? false, FILTER_VALIDATE_BOOL),
            page: max(1, (int) ($input['page'] ?? 1)),
            perPage: min(self::MAX_PAGE_SIZE, max(1, (int) ($input['per_page'] ?? 20))),
        );
    }

    private static function date(string $value): string
    {
        $value = trim($value);
        if ($value !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            throw new InvalidArgumentException('The submission date filter is invalid.');
        }

        return $value;
    }
}
