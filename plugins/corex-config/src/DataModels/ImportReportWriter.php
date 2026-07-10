<?php

/** @package Corex\Config */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use RuntimeException;

/** Emits a downloadable, spreadsheet-formula-safe rejected-row report. */
final class ImportReportWriter
{
    public function write(DataImportRun $run): string
    {
        $handle = fopen('php://temp', 'w+b');
        if ($handle === false) {
            throw new RuntimeException('The import report could not be created.');
        }
        fputcsv($handle, ['line', 'reason', ...$run->header]);
        foreach ($run->rejectedRows as $rejected) {
            fputcsv($handle, [
                (string) $rejected['line'],
                $this->safe((string) $rejected['reason']),
                ...array_map($this->safe(...), $rejected['row']),
            ]);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return is_string($csv) ? $csv : '';
    }

    private function safe(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value)) === 1 ? "'" . $value : $value;
    }
}
