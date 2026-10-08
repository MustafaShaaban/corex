<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Data\DataField;
use Corex\Data\DataSourceCapabilities;

/**
 * The reference DataSource: stored form submissions (`corex_submission` posts). Shapes each
 * record into id/date/form/summary; the WP_Query + meta access lives in the injected reader
 * so this shaping is unit-tested headlessly (spec 030).
 *
 * A submission has three shapes here, each for one reader: the table's row (a summary of the
 * answers), the detail view's record (each answer, labelled) and the export's row (each answer
 * under the field declared for it). They are shaped in this class and nowhere else.
 */
final class SubmissionsSource implements ExportableDataSource, SchemaAwareDataSource, TrendableDataSource, CapabilityAwareDataSource
{
    /** The fields every submission has. An answer keyed like one of them gets no field of its own. */
    private const OWN_FIELDS = ['date', 'form', 'summary'];

    /** How many recent submissions are read to learn which answers there are. */
    private const ANSWER_SAMPLE = 50;

    public function __construct(private readonly SubmissionsReader $reader)
    {
    }

    public function key(): string
    {
        return 'submissions';
    }

    public function label(): string
    {
        return __('Form submissions', 'corex');
    }

    /**
     * @return list<array{id:string,label:string}>
     */
    public function columns(): array
    {
        return [
            ['id' => 'date', 'label' => __('Date', 'corex')],
            ['id' => 'form', 'label' => __('Form', 'corex')],
            ['id' => 'summary', 'label' => __('Submission', 'corex')],
        ];
    }

    /**
     * @return list<array<string,scalar>>
     */
    public function rows(int $page, int $perPage): array
    {
        return array_map($this->tableRow(...), $this->reader->page(max(1, $page), max(1, $perPage)));
    }

    public function total(): int
    {
        return $this->reader->total();
    }

    /**
     * @return list<array<string,scalar>>
     */
    public function query(DataQuery $query): array
    {
        return array_map($this->tableRow(...), $this->reader->query($query));
    }

    public function exportRows(DataQuery $query): array
    {
        return $this->asExportRows($this->reader->query($query));
    }

    public function exportRowsOf(array $ids): array
    {
        return $this->asExportRows(array_values(array_filter(array_map($this->reader->find(...), $ids))));
    }

    public function count(DataQuery $query): int
    {
        return $this->reader->count($query);
    }

    /**
     * @return array{id:int,date:string,form:string,fields:list<array{label:string,value:string}>}|null
     */
    public function record(int $id): ?array
    {
        $record = $this->reader->find($id);

        if ($record === null) {
            return null;
        }

        return [
            'id'     => $record['id'],
            'date'   => $record['date'],
            'form'   => $record['form'],
            'fields' => $this->labelFields($record['fields']),
        ];
    }

    public function delete(int $id): bool
    {
        return $this->reader->trash($id);
    }

    public function capabilities(): DataSourceCapabilities
    {
        return new DataSourceCapabilities(
            sourceKey: $this->key(),
            read: true,
            query: true,
            schema: true,
            detail: true,
            create: false,
            update: false,
            delete: true,
            bulkUpdate: false,
            bulkDelete: false,
            importDryRun: false,
            importCommit: false,
            exportCsv: true,
            exportXlsx: true,
            migrations: false,
            rollback: false,
            maxPageSize: 100,
            permissionMap: [
                DataSourceCapabilities::READ       => CorexAbility::MANAGE_SUBMISSIONS,
                DataSourceCapabilities::QUERY      => CorexAbility::MANAGE_SUBMISSIONS,
                DataSourceCapabilities::SCHEMA     => CorexAbility::MANAGE_SUBMISSIONS,
                DataSourceCapabilities::DETAIL     => CorexAbility::MANAGE_SUBMISSIONS,
                DataSourceCapabilities::DELETE     => CorexAbility::MANAGE_SUBMISSIONS,
                DataSourceCapabilities::EXPORT_CSV => CorexAbility::MANAGE_SUBMISSIONS,
                DataSourceCapabilities::EXPORT_XLSX => CorexAbility::MANAGE_SUBMISSIONS,
            ],
        );
    }

    public function fields(): array
    {
        $fields = [
            new DataField('date', __('Submitted', 'corex'), DataField::TYPE_DATETIME, false, true, true, [], true, DataField::PERSONAL_NONE, [], []),
            new DataField('form', __('Form', 'corex'), DataField::TYPE_FORM, false, true, true, ['equals'], false, DataField::PERSONAL_NONE, [], []),
            new DataField('summary', __('Submission', 'corex'), DataField::TYPE_TEXTAREA, false, true, true, [], false, DataField::PERSONAL_CONTENT, [], []),
        ];
        foreach ($this->answerFields() as $fieldKey => $answerKey) {
            $type = $this->inferType($answerKey);
            $fields[] = new DataField(
                key: $fieldKey,
                label: ucwords(str_replace(['_', '-'], ' ', $answerKey)),
                type: $type,
                required: false,
                nullable: true,
                readOnly: true,
                filterOperators: [],
                sortable: false,
                personalDataClass: match ($type) {
                    DataField::TYPE_EMAIL, DataField::TYPE_TEL => DataField::PERSONAL_CONTACT,
                    DataField::TYPE_TEXTAREA => DataField::PERSONAL_CONTENT,
                    default => DataField::PERSONAL_NONE,
                },
                validation: [],
                importAliases: [],
            );
        }

        return $fields;
    }

    /**
     * The real field schema: the fixed record id / submitted date / form columns plus the
     * actual submitted payload keys discovered across recent submissions, each given a
     * meaningful type (id/datetime/form/email/textarea/tel/text). No invented fields — when
     * no submissions exist only the three fixed fields are returned.
     *
     * @return list<array{name:string,type:string}>
     */
    public function schema(): array
    {
        $schema = [
            ['name' => __('Record ID', 'corex'), 'type' => 'id'],
            ['name' => __('Submitted', 'corex'), 'type' => 'datetime'],
            ['name' => __('Form', 'corex'), 'type' => 'form'],
        ];

        foreach ($this->reader->fieldKeys(self::ANSWER_SAMPLE) as $key) {
            $schema[] = [
                'name' => ucwords(str_replace(['_', '-'], ' ', $key)),
                'type' => $this->inferType($key),
            ];
        }

        return $schema;
    }

    /**
     * A meaningful field type inferred from the submitted key name.
     */
    private function inferType(string $key): string
    {
        $key = strtolower($key);

        return match (true) {
            str_contains($key, 'email')                                                          => 'email',
            str_contains($key, 'message') || str_contains($key, 'comment') || str_contains($key, 'body') => 'textarea',
            str_contains($key, 'phone') || str_contains($key, 'tel')                              => 'tel',
            str_contains($key, 'url') || str_contains($key, 'website')                            => 'url',
            default                                                                              => 'text',
        };
    }

    /**
     * Real per-day submission counts for the last $days days, oldest first, every day present
     * (missing days are a truthful zero — never fabricated).
     *
     * @return list<array{date:string,count:int}>
     */
    public function trend(int $days): array
    {
        $days   = max($days, 1);
        $counts = $this->reader->dailyCounts($days);
        $out    = [];

        for ($offset = $days - 1; $offset >= 0; $offset--) {
            $date  = gmdate('Y-m-d', time() - $offset * DAY_IN_SECONDS);
            $out[] = ['date' => $date, 'count' => $counts[$date] ?? 0];
        }

        return $out;
    }

    /**
     * The row the Records table shows: the answers as one summary, because which answers there
     * are differs from form to form and a table has one set of columns.
     *
     * @param array{id:int,date:string,form:string,fields:array<string,mixed>} $record
     *
     * @return array{id:int,date:string,form:string,summary:string}
     */
    private function tableRow(array $record): array
    {
        return [
            'id'      => $record['id'],
            'date'    => $record['date'],
            'form'    => $record['form'],
            'summary' => $this->summarize($record['fields']),
        ];
    }

    /**
     * @param list<array{id:int,date:string,form:string,fields:array<string,mixed>}> $records
     *
     * @return list<array<string,mixed>>
     */
    private function asExportRows(array $records): array
    {
        $unanswered = array_fill_keys(array_keys($this->answerFields()), '');

        return array_map(fn (array $record): array => $this->exportRow($record, $unanswered), $records);
    }

    /**
     * The row an export writes: a value under every field `fields()` declares, and nothing else.
     * An answer is handed over as it was stored; what a list reads as in a cell is the export's
     * to say, as it is for every other source.
     *
     * @param array{id:int,date:string,form:string,fields:array<string,mixed>} $record
     * @param array<string,string>                                            $unanswered Every answer field, empty.
     *
     * @return array<string,mixed>
     */
    private function exportRow(array $record, array $unanswered): array
    {
        $row = [
            'date'    => $record['date'],
            'form'    => $record['form'],
            'summary' => $this->summarize($record['fields']),
        ] + $unanswered;

        foreach ($record['fields'] as $answerKey => $value) {
            $fieldKey = $this->fieldKey((string) $answerKey);
            // Only into a declared field that is still empty: where two answers' keys differ only
            // in how they were written, the first is the one the column holds.
            if ($fieldKey !== null && ($row[$fieldKey] ?? null) === '') {
                $row[$fieldKey] = $value;
            }
        }

        return $row;
    }

    /**
     * The field each answer is declared as, read from recent submissions: field key => the
     * answer's key as it was submitted. `fields()` and the export row are both built from this,
     * so a column the export offers is a column the row fills.
     *
     * @return array<string,string>
     */
    private function answerFields(): array
    {
        $fields = [];
        foreach ($this->reader->fieldKeys(self::ANSWER_SAMPLE) as $answerKey) {
            // Cast, here and for a record's own answers: PHP keys an array by integer where a
            // key is all digits, so an answer keyed "1" arrives as 1.
            $fieldKey = $this->fieldKey((string) $answerKey);
            if ($fieldKey !== null && ! isset($fields[$fieldKey])) {
                $fields[$fieldKey] = (string) $answerKey;
            }
        }

        return $fields;
    }

    /** The key of the field an answer is read under, or null when it has no field of its own. */
    private function fieldKey(string $answerKey): ?string
    {
        $fieldKey = sanitize_key($answerKey);

        return preg_match('/^[a-z][a-z0-9_-]*$/', $fieldKey) === 1 && ! in_array($fieldKey, self::OWN_FIELDS, true)
            ? $fieldKey
            : null;
    }

    /**
     * Shape a submission's raw field map into readable label → value pairs for the detail
     * view (spec 045, US3) — the field key humanised, the value stringified. No secret.
     *
     * @param array<string,mixed> $fields
     *
     * @return list<array{label:string,value:string}>
     */
    private function labelFields(array $fields): array
    {
        $out = [];

        foreach ($fields as $name => $value) {
            $out[] = [
                'label' => ucwords(str_replace(['_', '-'], ' ', (string) $name)),
                'value' => is_scalar($value) ? (string) $value : (string) wp_json_encode($value),
            ];
        }

        return $out;
    }

    /**
     * A compact, plain-text "key: value · …" summary of a submission's fields.
     *
     * @param array<string,mixed> $fields
     */
    private function summarize(array $fields): string
    {
        $parts = [];
        foreach ($fields as $name => $value) {
            $parts[] = $name . ': ' . (is_scalar($value) ? (string) $value : (string) wp_json_encode($value));
        }

        return implode(' · ', $parts);
    }
}
