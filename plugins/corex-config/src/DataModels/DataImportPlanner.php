<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Corex\Data\DataField;
use InvalidArgumentException;

/** Builds the immutable accepted/rejected projection used by preview and commit. */
final class DataImportPlanner
{
    /** @param list<DataField> $fields @return array<string,mixed> */
    public static function plan(DataImportRequest $request, array $fields): array
    {
        [$fieldMap, $aliases] = self::fieldMaps($fields);
        [$mapping, $unknown] = self::mapping($request, $fieldMap, $aliases);
        $accepted = [];
        $rejected = [];
        foreach ($request->rows as $offset => $row) {
            $reason = self::rejectReason($request, $row, $mapping, $unknown, $fieldMap);
            if ($reason !== null) {
                $rejected[] = ['line' => $offset + 2, 'reason' => $reason, 'row' => $row];
                continue;
            }
            $accepted[] = self::values($request->header, $row, $mapping);
        }

        return [
            'mapping' => $mapping,
            'unknown_columns' => $unknown,
            'accepted_rows' => $accepted,
            'rejected_rows' => $rejected,
            'personal_data_classes' => self::personalClasses($mapping, $fieldMap),
        ];
    }

    /** @param list<DataField> $fields @return array{array<string,DataField>,array<string,string>} */
    private static function fieldMaps(array $fields): array
    {
        $fieldMap = [];
        $aliases = [];
        foreach ($fields as $field) {
            $fieldMap[$field->key] = $field;
            foreach ($field->importAliases as $alias) {
                $aliases[$alias] = $field->key;
            }
        }

        return [$fieldMap, $aliases];
    }

    /** @param array<string,DataField> $fields @param array<string,string> $aliases @return array{array<string,string>,list<string>} */
    private static function mapping(DataImportRequest $request, array $fields, array $aliases): array
    {
        $mapping = [];
        $unknown = [];
        foreach ($request->header as $column) {
            $target = array_key_exists($column, $request->mapping)
                ? $request->mapping[$column]
                : ($fields[$column]->key ?? $aliases[$column] ?? '');
            if ($target === '') {
                $unknown[] = $column;
                continue;
            }
            if (! isset($fields[$target])) {
                throw new InvalidArgumentException('The import mapping targets an unknown field.');
            }
            if ($fields[$target]->readOnly) {
                throw new InvalidArgumentException('The import mapping targets a read-only field.');
            }
            if (in_array($target, $mapping, true)) {
                throw new InvalidArgumentException('The import mapping targets a field more than once.');
            }
            $mapping[$column] = $target;
        }

        return [$mapping, $unknown];
    }

    /** @param list<string> $row @param array<string,string> $mapping @param list<string> $unknown @param array<string,DataField> $fields */
    private static function rejectReason(
        DataImportRequest $request,
        array $row,
        array $mapping,
        array $unknown,
        array $fields,
    ): ?string {
        if (count($row) !== count($request->header)) {
            return __('Column count does not match the header.', 'corex');
        }
        if ($unknown !== [] && $request->unknownPolicy === DataImportRequest::UNKNOWN_REJECT) {
            /* translators: %s: comma-separated unknown CSV columns. */
            return sprintf(__('Unknown column: %s.', 'corex'), implode(', ', $unknown));
        }
        $values = self::values($request->header, $row, $mapping);
        foreach ($fields as $field) {
            $value = $values[$field->key] ?? null;
            $reason = self::fieldReason($field, $value);
            if ($reason !== null) {
                return $reason;
            }
        }

        return null;
    }

    private static function fieldReason(DataField $field, mixed $value): ?string
    {
        if ($field->readOnly) {
            return null;
        }
        if ($field->required && ($value === null || trim((string) $value) === '')) {
            /* translators: %s: field label. */
            return sprintf(__('%s is required.', 'corex'), $field->label);
        }
        if ($value === null || $value === '') {
            return null;
        }
        if ($field->type === DataField::TYPE_EMAIL && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            /* translators: %s: field label. */
            return sprintf(__('%s must be a valid email.', 'corex'), $field->label);
        }
        $options = $field->validation['options'] ?? null;
        if ($field->type === DataField::TYPE_SELECT && is_array($options) && ! in_array($value, $options, true)) {
            /* translators: %s: field label. */
            return sprintf(__('%s is not an allowed value.', 'corex'), $field->label);
        }
        $maxLength = (int) ($field->validation['max_length'] ?? 0);
        if ($maxLength > 0 && mb_strlen((string) $value) > $maxLength) {
            /* translators: %s: field label. */
            return sprintf(__('%s is too long.', 'corex'), $field->label);
        }

        return null;
    }

    /** @param list<string> $header @param list<string> $row @param array<string,string> $mapping @return array<string,mixed> */
    private static function values(array $header, array $row, array $mapping): array
    {
        $values = [];
        foreach ($header as $index => $column) {
            if (isset($mapping[$column])) {
                $values[$mapping[$column]] = trim((string) ($row[$index] ?? ''));
            }
        }

        return $values;
    }

    /** @param array<string,string> $mapping @param array<string,DataField> $fields @return list<string> */
    private static function personalClasses(array $mapping, array $fields): array
    {
        $classes = [];
        foreach ($mapping as $fieldKey) {
            $class = $fields[$fieldKey]->personalDataClass;
            if ($class !== DataField::PERSONAL_NONE) {
                $classes[] = $class;
            }
        }
        $classes = array_values(array_unique($classes));
        sort($classes);

        return $classes;
    }
}
