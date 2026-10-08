<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportCell;
use Closure;
use Corex\Config\Export\ExportSheet;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Turns submissions into the table an export is written from (spec 103, FR-001 to FR-007).
 *
 * One row per submission. The columns a person looks for first, then one column per answer headed
 * by the question the form asked, then any optional group with a column per value. Every writer
 * takes this table, so what a column holds is decided once and no format decides it differently.
 */
final readonly class SubmissionExportTable
{
    public const ANSWERS = 'answers';
    public const ANSWER_PREFIX = 'answer:';
    public const NOTES = 'notes';

    /** The columns that say what a submission is, in the order they are read. */
    public const FIXED_COLUMNS = ['id', 'submitted', 'form', 'status', 'assigned_to', 'read', 'test'];

    /** Optional groups, each exported as one column per value recorded. */
    public const GROUP_COLUMNS = ['hidden_metadata', 'utm', 'consent_snapshot'];

    public const DEFAULT_COLUMNS = [...self::FIXED_COLUMNS, self::ANSWERS];

    private const NUMBER_TYPES = ['number', 'rating'];
    private const SEVERAL_VALUES = '; ';

    /**
     * @param Closure():DateTimeZone $siteTimezone Asked each time a date is written, not once: the
     *                                             table outlives a request under WP-CLI and in a
     *                                             test, and a site's timezone is a setting.
     */
    public function __construct(private SubmissionOwnerNames $owners, private Closure $siteTimezone)
    {
    }

    /**
     * @param iterable<array<string,mixed>>                    $records   Read twice: pass an array or
     *                                                                    anything that can be iterated again.
     * @param list<array{key:string,label:string,type:string}> $questions The form's questions, in its order.
     *                                                                    Empty when they are not available.
     * @param list<string>                                     $columns   Column ids, in the order wanted.
     */
    public function sheet(string $name, iterable $records, array $questions, array $columns): ExportSheet
    {
        $layout = $this->layout($questions, $columns, $this->keysSeen($records));

        return new ExportSheet(
            $name,
            array_column($layout, 'heading'),
            $this->rows($records, $layout),
        );
    }

    /**
     * @param iterable<array<string,mixed>>                        $records
     * @param list<array{heading:string,source:string,key:string,type:string}> $layout
     *
     * @return iterable<list<ExportCell>>
     */
    private function rows(iterable $records, array $layout): iterable
    {
        foreach ($records as $record) {
            yield array_map(fn (array $column): ExportCell => $this->cell($record, $column), $layout);
        }
    }

    /**
     * Every answer key and every group key that any record carries, in the order first seen.
     *
     * @param iterable<array<string,mixed>> $records
     *
     * @return array<string,list<string>> Source ('values', a group name) => keys.
     */
    private function keysSeen(iterable $records): array
    {
        $seen = array_fill_keys(['values', ...self::GROUP_COLUMNS], []);

        foreach ($records as $record) {
            foreach (array_keys($seen) as $source) {
                foreach (array_keys((array) ($record[$source] ?? [])) as $key) {
                    $seen[$source][(string) $key] = true;
                }
            }
        }

        return array_map(static fn (array $keys): array => array_map('strval', array_keys($keys)), $seen);
    }

    /**
     * @param list<array{key:string,label:string,type:string}> $questions
     * @param list<string>                                     $columns
     * @param array<string,list<string>>                       $seen
     *
     * @return list<array{heading:string,source:string,key:string,type:string}>
     */
    private function layout(array $questions, array $columns, array $seen): array
    {
        $asked  = array_column($questions, null, 'key');
        $layout = [];

        foreach ($columns as $column) {
            array_push($layout, ...match (true) {
                in_array($column, self::FIXED_COLUMNS, true) => [$this->fixedColumn($column)],
                $column === self::ANSWERS => $this->answerColumns(
                    [...array_keys($asked), ...array_diff($seen['values'], array_keys($asked))],
                    $asked,
                ),
                str_starts_with($column, self::ANSWER_PREFIX) => $this->answerColumns(
                    [substr($column, strlen(self::ANSWER_PREFIX))],
                    $asked,
                ),
                in_array($column, self::GROUP_COLUMNS, true) => $this->groupColumns($column, $seen[$column]),
                $column === self::NOTES => [$this->column(__('Notes', 'corex'), self::NOTES)],
                default => [],
            });
        }

        return $this->withDistinctHeadings($layout);
    }

    /**
     * @return array{heading:string,source:string,key:string,type:string}
     */
    private function fixedColumn(string $column): array
    {
        return $this->column(match ($column) {
            'id' => __('ID', 'corex'),
            'submitted' => __('Submitted', 'corex'),
            'form' => __('Form', 'corex'),
            'status' => __('Status', 'corex'),
            'assigned_to' => __('Assigned to', 'corex'),
            'read' => __('Read', 'corex'),
            'test' => __('Test', 'corex'),
        }, $column);
    }

    /**
     * @param list<string>                                      $keys
     * @param array<string,array{key:string,label:string,type:string}> $asked
     *
     * @return list<array{heading:string,source:string,key:string,type:string}>
     */
    private function answerColumns(array $keys, array $asked): array
    {
        return array_map(
            fn (string $key): array => $this->column(
                (string) ($asked[$key]['label'] ?? '') !== '' ? (string) $asked[$key]['label'] : $key,
                'values',
                $key,
                (string) ($asked[$key]['type'] ?? 'text'),
            ),
            array_values(array_map('strval', $keys)),
        );
    }

    /**
     * @param list<string> $keys
     *
     * @return list<array{heading:string,source:string,key:string,type:string}>
     */
    private function groupColumns(string $group, array $keys): array
    {
        $prefix = match ($group) {
            'hidden_metadata' => __('Hidden', 'corex'),
            'utm' => __('Campaign', 'corex'),
            'consent_snapshot' => __('Consent', 'corex'),
        };

        return array_map(
            /* translators: 1: the group a value belongs to, e.g. "Campaign". 2: the value's name, e.g. "source". */
            fn (string $key): array => $this->column(sprintf(__('%1$s: %2$s', 'corex'), $prefix, $key), $group, $key),
            $keys,
        );
    }

    /**
     * @return array{heading:string,source:string,key:string,type:string}
     */
    private function column(string $heading, string $source, string $key = '', string $type = 'text'): array
    {
        return ['heading' => $heading, 'source' => $source, 'key' => $key, 'type' => $type];
    }

    /**
     * Two questions may share their wording. The second and later say which they are.
     *
     * @param list<array{heading:string,source:string,key:string,type:string}> $layout
     *
     * @return list<array{heading:string,source:string,key:string,type:string}>
     */
    private function withDistinctHeadings(array $layout): array
    {
        $used = [];

        foreach ($layout as $index => $column) {
            if (isset($used[$column['heading']]) && $column['key'] !== '') {
                $layout[$index]['heading'] = sprintf('%s (%s)', $column['heading'], $column['key']);
            }
            $used[$column['heading']] = true;
        }

        return $layout;
    }

    /**
     * @param array<string,mixed>                                       $record
     * @param array{heading:string,source:string,key:string,type:string} $column
     */
    private function cell(array $record, array $column): ExportCell
    {
        return match ($column['source']) {
            'id' => ExportCell::number((int) ($record['id'] ?? 0)),
            'submitted' => $this->submitted((string) ($record['created_at'] ?? '')),
            'form' => ExportCell::text((string) (($record['flow'] ?? '') ?: ($record['form'] ?? ''))),
            'status' => ExportCell::text(self::statusName((string) ($record['status'] ?? 'new'))),
            'assigned_to' => ExportCell::text($this->owner($record)),
            'read' => ExportCell::text($this->yesNo(! empty($record['read_at']))),
            'test' => ExportCell::text($this->yesNo(! empty($record['is_test']))),
            self::NOTES => ExportCell::text($this->notes((array) ($record['notes'] ?? []))),
            'values' => $this->answer(((array) ($record['values'] ?? []))[$column['key']] ?? null, $column['type']),
            default => ExportCell::text($this->words(((array) ($record[$column['source']] ?? []))[$column['key']] ?? null)),
        };
    }

    /**
     * Stored in UTC; read in the site's own time.
     */
    private function submitted(string $storedUtc): ExportCell
    {
        $moment = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $storedUtc, new DateTimeZone('UTC'));

        return $moment === false
            ? ExportCell::text($storedUtc)
            : ExportCell::dateTime($moment->setTimezone(($this->siteTimezone)()));
    }

    /**
     * A status as a person reads it. Said in the table's Status column and in a document's line
     * about the filters it was made under.
     */
    public static function statusName(string $status): string
    {
        return match ($status) {
            'new' => __('New', 'corex'),
            'in_progress' => __('In progress', 'corex'),
            'replied' => __('Replied', 'corex'),
            'closed' => __('Closed', 'corex'),
            'spam' => __('Spam', 'corex'),
            'archived' => __('Archived', 'corex'),
            default => $status,
        };
    }

    /**
     * @param array<string,mixed> $record
     */
    private function owner(array $record): string
    {
        $type = (string) ($record['owner_type'] ?? 'none');
        $key  = (string) ($record['owner_key'] ?? '');

        if ($type === 'none' || $type === '') {
            return __('Unassigned', 'corex');
        }

        $name = $this->owners->nameOf($type, $key);
        if ($name !== '') {
            return $name;
        }

        // Routed to whoever owns the form: there is no key, and no person to name.
        if ($type === 'flow_owner') {
            return __('The form’s owner', 'corex');
        }

        return $key === '' ? $type : sprintf('%s: %s', $type, $key);
    }

    private function yesNo(bool $value): string
    {
        return $value ? __('Yes', 'corex') : __('No', 'corex');
    }

    /**
     * @param array<int|string,mixed> $notes
     */
    private function notes(array $notes): string
    {
        return implode("\n", array_filter(array_map(
            static fn (mixed $note): string => is_array($note) ? trim((string) ($note['body'] ?? '')) : '',
            $notes,
        )));
    }

    /**
     * A number only where the form asked for one: a phone number that is all digits stays text.
     */
    private function answer(mixed $value, string $type): ExportCell
    {
        if (in_array($type, self::NUMBER_TYPES, true) && is_numeric($value)) {
            return ExportCell::number($value + 0);
        }

        return ExportCell::text($this->words($value));
    }

    /**
     * A stored value as a person would say it.
     */
    private function words(mixed $value): string
    {
        if (is_bool($value)) {
            return $this->yesNo($value);
        }

        if (! is_array($value)) {
            return (string) ($value ?? '');
        }

        // An uploaded file is stored as its details; its name is what a person calls it.
        if (isset($value['name']) && is_scalar($value['name'])) {
            return (string) $value['name'];
        }

        return implode(self::SEVERAL_VALUES, array_filter(
            array_map(fn (mixed $item): string => $this->words($item), $value),
            static fn (string $item): bool => $item !== '',
        ));
    }
}
