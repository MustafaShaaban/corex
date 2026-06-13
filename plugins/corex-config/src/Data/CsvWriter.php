<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * Renders a data source's columns + rows as an RFC-4180 CSV string (spec 045, US2). Pure:
 * a header row from the column labels, one row per record, every field escaped (quotes
 * doubled; fields containing a comma/quote/newline quoted). Only the **declared columns**
 * are emitted, so no internal/secret field can leak (SC-005).
 */
final class CsvWriter
{
    /**
     * @param list<array{id:string,label:string}> $columns
     * @param list<array<string,scalar>>           $rows
     */
    public function write(array $columns, array $rows): string
    {
        $ids = array_column($columns, 'id');

        $csv = $this->line(array_column($columns, 'label'));

        foreach ($rows as $row) {
            $csv .= $this->line(array_map(
                static fn (string $id): string => (string) ($row[$id] ?? ''),
                $ids,
            ));
        }

        return $csv;
    }

    /**
     * @param list<string> $values
     */
    private function line(array $values): string
    {
        return implode(',', array_map([$this, 'escape'], $values)) . "\r\n";
    }

    private function escape(string $value): string
    {
        if (preg_match('/[",\r\n]/', $value) === 1) {
            return '"' . str_replace('"', '""', $value) . '"';
        }

        return $value;
    }
}
