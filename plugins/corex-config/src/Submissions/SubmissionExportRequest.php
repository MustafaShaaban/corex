<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportWriters;
use InvalidArgumentException;

/**
 * Validated export scope, columns and format. Tests remain excluded by default.
 */
final readonly class SubmissionExportRequest
{
    public const SCOPES = ['accessible', 'filtered', 'selected'];

    /**
     * The groups an export was asked for before it had a column per value (spec 068), and the
     * columns each now stands for. A request that names one still means what it meant.
     */
    public const GROUPS = [
        'identity' => ['id', 'submitted', 'form'],
        'workflow' => ['status', 'assigned_to', 'read', 'test'],
        'submitted_fields' => [SubmissionExportTable::ANSWERS],
    ];

    /** Columns that hold what a visitor said, or what was recorded about them. */
    public const PERSONAL_COLUMNS = [
        SubmissionExportTable::ANSWERS,
        'hidden_metadata',
        'utm',
        'consent_snapshot',
        SubmissionExportTable::NOTES,
    ];

    private const ONE_ANSWER = '/^' . SubmissionExportTable::ANSWER_PREFIX . '[A-Za-z0-9_\-]+$/';

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
        public string $separator,
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

        return new self(
            scope: $scope,
            selectedIds: $ids,
            columns: self::columns((array) ($input['columns'] ?? [])),
            query: is_array($input['query'] ?? null) ? $input['query'] : [],
            includeTest: filter_var($input['include_test'] ?? false, FILTER_VALIDATE_BOOL),
            personalDataAcknowledged: filter_var($input['personal_data_acknowledged'] ?? false, FILTER_VALIDATE_BOOL),
            format: self::format((string) ($input['format'] ?? ExportWriters::CSV)),
            separator: self::separator((string) ($input['separator'] ?? ExportWriters::DEFAULT_SEPARATOR)),
        );
    }

    public function includesPersonalData(): bool
    {
        foreach ($this->columns as $column) {
            if (in_array($column, self::PERSONAL_COLUMNS, true) || self::isOneAnswer($column)) {
                return true;
            }
        }

        return false;
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
            'separator' => $this->separator,
        ];
    }

    /**
     * The columns asked for, in the order asked, with a group replaced by the columns it stands for.
     *
     * @param array<int|string,mixed> $asked
     *
     * @return list<string>
     */
    private static function columns(array $asked): array
    {
        $columns = [];
        foreach ($asked as $column) {
            $column = (string) $column;
            foreach (self::GROUPS[$column] ?? [$column] as $resolved) {
                if (! self::isColumn($resolved)) {
                    throw new InvalidArgumentException('The submission export columns are invalid.');
                }
                $columns[$resolved] = true;
            }
        }

        if ($columns === []) {
            throw new InvalidArgumentException('The submission export columns are invalid.');
        }

        return array_map('strval', array_keys($columns));
    }

    private static function isColumn(string $column): bool
    {
        return in_array($column, SubmissionExportTable::FIXED_COLUMNS, true)
            || in_array($column, SubmissionExportTable::GROUP_COLUMNS, true)
            || in_array($column, [SubmissionExportTable::ANSWERS, SubmissionExportTable::NOTES], true)
            || self::isOneAnswer($column);
    }

    private static function isOneAnswer(string $column): bool
    {
        return preg_match(self::ONE_ANSWER, $column) === 1;
    }

    private static function format(string $format): string
    {
        if (! in_array($format, ExportWriters::FORMATS, true)) {
            throw new InvalidArgumentException('The submission export format is invalid.');
        }

        return $format;
    }

    private static function separator(string $separator): string
    {
        if (! isset(ExportWriters::SEPARATORS[$separator])) {
            throw new InvalidArgumentException('The submission export separator is invalid.');
        }

        return $separator;
    }
}
