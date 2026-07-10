<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * The query a {@see DataSource} answers (spec 045): a substring search, source filters
 * (e.g. `form`), a sort column + direction, and pagination. A pure value object built from
 * (already-sanitised) request params, clamping page/per-page to a safe range. A source that
 * doesn't support a given sort column ignores it (default order) — never an error.
 */
final class DataQuery
{
    public const MAX_PER_PAGE = 100;

    /**
     * @param array<string,string> $filters
     */
    private function __construct(
        public readonly string $search,
        public readonly array $filters,
        public readonly string $sortColumn,
        public readonly string $sortDir,
        public readonly int $page,
        public readonly int $perPage,
    ) {
    }

    /**
     * @param array<string,mixed> $params
     */
    public static function from(array $params): self
    {
        $filters = is_array($params['filters'] ?? null) ? $params['filters'] : [];

        if (! empty($params['form'])) {
            $filters['form'] = (string) $params['form'];
        }

        return new self(
            trim((string) ($params['search'] ?? '')),
            array_map(static fn ($value): string => (string) $value, $filters),
            (string) ($params['sort'] ?? ''),
            strtolower((string) ($params['dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc',
            max(1, (int) ($params['page'] ?? 1)),
            max(1, min(self::MAX_PER_PAGE, (int) ($params['per_page'] ?? 20))),
        );
    }

    public function withPerPage(int $perPage): self
    {
        return new self(
            $this->search,
            $this->filters,
            $this->sortColumn,
            $this->sortDir,
            $this->page,
            max(1, min(self::MAX_PER_PAGE, $perPage)),
        );
    }
}
