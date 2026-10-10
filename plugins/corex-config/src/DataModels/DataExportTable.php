<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Closure;
use Corex\Config\Data\ReadableValue;
use Corex\Config\Export\ExportCell;
use Corex\Config\Export\ExportSheet;
use Corex\Data\DataField;
use DateTimeImmutable;
use DateTimeZone;
use Exception;

/**
 * A Data source's records as a table a person reads (spec 103, US10).
 *
 * A source declares what each of its fields is. That is what decides how a value is written: a
 * number as a number, a date and time as one, a switch as yes or no, a list as its items. The
 * export wrote every value as `(string) $value`, which made a list the word "Array".
 */
final readonly class DataExportTable
{
    private const NUMBER_TYPES = [DataField::TYPE_ID, DataField::TYPE_INTEGER, DataField::TYPE_DECIMAL];

    /** What MySQL stores for a date and time that was never set. It is a date to no reader. */
    private const NEVER_SET = '0000-00-00';

    /**
     * A date, then a time, then perhaps where it is from. PHP reads far more than this as a date:
     * "next week" is one to it, and a field holding those words is not holding a date.
     */
    private const STORED_DATETIME = '/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?$/';

    /** @param Closure():DateTimeZone $timezone The timezone the site reads its dates in. */
    public function __construct(private Closure $timezone)
    {
    }

    /**
     * @param list<DataField>                $fields  Every field the source declares.
     * @param list<string>                   $columns The keys of the fields asked for, in order.
     * @param iterable<array<string,mixed>>  $records
     */
    public function sheet(string $name, array $fields, array $columns, iterable $records): ExportSheet
    {
        $asked = $this->asked($fields, $columns);

        return new ExportSheet(
            $name,
            array_map(static fn (DataField $field): string => $field->label, $asked),
            $this->rows($asked, $records),
        );
    }

    /**
     * @param list<DataField> $fields
     * @param list<string>    $columns
     *
     * @return list<DataField> The fields asked for, in the order they were asked for.
     */
    private function asked(array $fields, array $columns): array
    {
        $byKey = [];
        foreach ($fields as $field) {
            $byKey[$field->key] = $field;
        }

        return array_values(array_filter(array_map(
            static fn (string $key): ?DataField => $byKey[$key] ?? null,
            $columns,
        )));
    }

    /**
     * @param list<DataField>               $fields
     * @param iterable<array<string,mixed>> $records
     *
     * @return iterable<list<ExportCell>>
     */
    private function rows(array $fields, iterable $records): iterable
    {
        foreach ($records as $record) {
            yield array_map(fn (DataField $field): ExportCell => $this->cell($field, $record[$field->key] ?? null), $fields);
        }
    }

    private function cell(DataField $field, mixed $value): ExportCell
    {
        if ($field->type === DataField::TYPE_BOOLEAN) {
            return ExportCell::text($this->truthy($value) ? __('Yes', 'corex') : __('No', 'corex'));
        }
        if (in_array($field->type, self::NUMBER_TYPES, true) && is_numeric($value)) {
            return ExportCell::number($field->type === DataField::TYPE_DECIMAL ? (float) $value : (int) $value);
        }
        if ($field->type === DataField::TYPE_DATETIME && is_string($value)) {
            $instant = $this->instant($value);
            if ($instant !== null) {
                return ExportCell::dateTime($instant);
            }
        }

        // A list as its items, anything deeper as what it holds: as the Data screen reads it.
        return ExportCell::text(ReadableValue::of($value));
    }

    private function truthy(mixed $value): bool
    {
        return filter_var($value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * A stored date and time, in the site's time. A value that says where it is from is moved to
     * it; one that does not is read as it was stored, because nothing says it should be moved.
     */
    private function instant(string $stored): ?DateTimeImmutable
    {
        if (preg_match(self::STORED_DATETIME, $stored) !== 1 || str_starts_with($stored, self::NEVER_SET)) {
            return null;
        }
        $timezone = ($this->timezone)();

        try {
            return (new DateTimeImmutable($stored, $timezone))->setTimezone($timezone);
        } catch (Exception) {
            // Not a date and time a reader would recognise: it is written as it was stored.
            return null;
        }
    }
}
