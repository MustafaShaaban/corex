<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Validated export scope and column selection. Tests remain excluded by default.
 */
final readonly class SubmissionExportRequest
{
    public const SCOPES = ['accessible', 'filtered', 'selected'];
    public const COLUMNS = [
        'identity',
        'workflow',
        'submitted_fields',
        'hidden_metadata',
        'utm',
        'consent_snapshot',
        'notes',
    ];
    public const PERSONAL_COLUMNS = ['submitted_fields', 'hidden_metadata', 'utm', 'consent_snapshot', 'notes'];

    /**
     * @param list<int> $selectedIds
     * @param list<string> $columns
     * @param array<string,mixed> $query
     */
    private function __construct(
        public string $scope,
        public array $selectedIds,
        public array $columns,
        public array $query,
        public bool $includeTest,
        public bool $personalDataAcknowledged,
        public string $format,
    ) {
    }

    /** @param array<string,mixed> $input */
    public static function from(array $input): self
    {
        $scope = (string) ($input['scope'] ?? 'filtered');
        if (! in_array($scope, self::SCOPES, true)) {
            throw new InvalidArgumentException('The submission export scope is invalid.');
        }
        $ids = array_values(array_unique(array_filter(
            array_map('intval', (array) ($input['selected_ids'] ?? [])),
            static fn (int $id): bool => $id > 0,
        )));
        sort($ids, SORT_NUMERIC);
        if ($scope === 'selected' && $ids === []) {
            throw new InvalidArgumentException('A selected export requires submission IDs.');
        }
        if (count($ids) > 100) {
            throw new InvalidArgumentException('Selected exports are limited to 100 submissions.');
        }

        $columns = array_values(array_unique(array_map('strval', (array) ($input['columns'] ?? []))));
        if ($columns === [] || array_diff($columns, self::COLUMNS) !== []) {
            throw new InvalidArgumentException('The submission export columns are invalid.');
        }

        return new self(
            scope: $scope,
            selectedIds: $ids,
            columns: $columns,
            query: is_array($input['query'] ?? null) ? $input['query'] : [],
            includeTest: filter_var($input['include_test'] ?? false, FILTER_VALIDATE_BOOL),
            personalDataAcknowledged: filter_var($input['personal_data_acknowledged'] ?? false, FILTER_VALIDATE_BOOL),
            format: 'csv',
        );
    }

    public function includesPersonalData(): bool
    {
        return array_intersect($this->columns, self::PERSONAL_COLUMNS) !== [];
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'scope' => $this->scope,
            'selected_ids' => $this->selectedIds,
            'columns' => $this->columns,
            'query' => $this->query,
            'include_test' => $this->includeTest,
            'personal_data_acknowledged' => $this->personalDataAcknowledged,
            'format' => $this->format,
        ];
    }
}
