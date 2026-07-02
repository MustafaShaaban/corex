<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

/**
 * A real CSV import dry-run (spec 065): validates a parsed CSV against a data model's known columns and
 * reports exactly what a real import would accept or reject — without writing anything. It is the
 * "dry-run must be real" contract: header columns are matched to the model's column ids, unknown/missing
 * columns are surfaced, and each row is accepted or rejected with a concrete reason (wrong column count,
 * or an all-empty row). WordPress-free, so it is unit-testable. It performs no persistence — committing
 * a validated import needs a per-model write adapter, which the current read-only sources do not expose.
 */
final class DataImportValidator
{
    /** Cap the rejected-row report so a pathological file cannot exhaust memory. */
    public const MAX_REPORTED = 50;

    /**
     * @param list<string>       $columnIds the model's known column ids
     * @param list<string>       $header    the CSV header cells, in order
     * @param list<list<string>> $rows      the CSV data rows (header excluded)
     *
     * @return array{
     *   matched: list<string>,
     *   unknown: list<string>,
     *   missing: list<string>,
     *   totalRows: int,
     *   accepted: int,
     *   rejected: list<array{line:int,reason:string}>
     * }
     */
    public function validate(array $columnIds, array $header, array $rows): array
    {
        $header  = array_map(static fn (string $h): string => trim($h), $header);
        $matched = array_values(array_intersect($header, $columnIds));
        $unknown = array_values(array_diff($header, $columnIds));
        $missing = array_values(array_diff($columnIds, $header));

        $accepted = 0;
        $rejected = [];
        $line     = 1; // header is line 1; first data row is line 2

        foreach ($rows as $row) {
            $line++;
            $reason = $this->rejectReason($row, count($header));
            if ($reason !== null) {
                if (count($rejected) < self::MAX_REPORTED) {
                    $rejected[] = ['line' => $line, 'reason' => $reason];
                }

                continue;
            }
            $accepted++;
        }

        return [
            'matched'   => $matched,
            'unknown'   => $unknown,
            'missing'   => $missing,
            'totalRows' => count($rows),
            'accepted'  => $accepted,
            'rejected'  => $rejected,
        ];
    }

    /**
     * @param list<string> $row
     */
    private function rejectReason(array $row, int $headerWidth): ?string
    {
        if ($headerWidth > 0 && count($row) !== $headerWidth) {
            return __('Column count does not match the header.', 'corex');
        }

        $hasValue = false;
        foreach ($row as $cell) {
            if (trim($cell) !== '') {
                $hasValue = true;
                break;
            }
        }

        return $hasValue ? null : __('Row is empty.', 'corex');
    }
}
