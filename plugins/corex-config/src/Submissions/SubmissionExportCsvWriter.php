<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * Formula-safe CSV serialization for explicitly selected submission data classes.
 */
final class SubmissionExportCsvWriter
{
    /** @param list<array<string,mixed>> $records @param list<string> $columns */
    public function write(array $records, array $columns, bool $includeHeader): string
    {
        $stream = fopen('php://temp', 'w+b');
        if ($stream === false) {
            throw new RuntimeException('CoreX could not open the export buffer.');
        }
        if ($includeHeader) {
            fputcsv($stream, $columns, ',', '"', '');
        }
        foreach ($records as $record) {
            fputcsv($stream, array_map(
                fn (string $column): string => $this->cell($record, $column),
                $columns,
            ), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return is_string($csv) ? $csv : '';
    }

    /** @param array<string,mixed> $record */
    private function cell(array $record, string $column): string
    {
        $value = match ($column) {
            'identity' => [
                'id' => $record['id'] ?? null,
                'flow' => $record['flow'] ?? '',
                'created_at' => $record['created_at'] ?? '',
                'is_test' => $record['is_test'] ?? false,
            ],
            'workflow' => [
                'status' => $record['status'] ?? '',
                'owner_type' => $record['owner_type'] ?? 'none',
                'owner_key' => $record['owner_key'] ?? '',
                'retention_state' => $record['retention_state'] ?? 'active',
            ],
            'submitted_fields' => $record['values'] ?? [],
            'hidden_metadata' => $record['hidden_metadata'] ?? [],
            'utm' => $record['utm'] ?? [],
            'consent_snapshot' => $record['consent_snapshot'] ?? [],
            'notes' => $record['notes'] ?? [],
            default => '',
        };
        $text = is_scalar($value) ? (string) $value : (string) wp_json_encode($value);

        return preg_match('/^[=+\-@]/', ltrim($text)) === 1 ? "'" . $text : $text;
    }
}
