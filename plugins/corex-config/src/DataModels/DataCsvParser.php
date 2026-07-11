<?php

/** @package Corex\Config */
declare(strict_types=1);
namespace Corex\Config\DataModels;
defined('ABSPATH') || exit;

use InvalidArgumentException;

/** Bounded server-side parser for WordPress REST CSV uploads. */
final class DataCsvParser
{
    private const MAX_BYTES = 2_000_000;
    private const MAX_ROWS = 5000;
    private const MAX_COLUMNS = 200;

    /** @param array<string,mixed> $file @return array{file_name:string,header:list<string>,rows:list<list<string>>} */
    public function parse(array $file): array
    {
        $path = (string) ($file['tmp_name'] ?? '');
        $size = (int) ($file['size'] ?? 0);
        $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
        $name = sanitize_file_name((string) ($file['name'] ?? 'import.csv'));
        if ($error !== UPLOAD_ERR_OK || $path === '' || ! is_readable($path)
            || $size < 1 || $size > self::MAX_BYTES || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'csv') {
            throw new InvalidArgumentException('The CSV upload is invalid or exceeds 2 MB.');
        }
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('The CSV upload could not be read.');
        }
        $header = $this->line($handle);
        if ($header === [] || count($header) > self::MAX_COLUMNS) {
            fclose($handle);
            throw new InvalidArgumentException('The CSV header is empty or too wide.');
        }
        $rows = [];
        while (count($rows) < self::MAX_ROWS && ($row = fgetcsv($handle)) !== false) {
            $rows[] = array_values(array_map('strval', (array) $row));
        }
        $truncated = fgetcsv($handle) !== false;
        fclose($handle);
        if ($truncated) {
            throw new InvalidArgumentException('The CSV upload exceeds 5000 rows.');
        }

        return ['file_name' => $name, 'header' => $header, 'rows' => $rows];
    }

    /** @param resource $handle @return list<string> */
    private function line($handle): array
    {
        $line = fgetcsv($handle);

        return $line === false ? [] : array_values(array_map(static fn (mixed $value): string => trim((string) $value), $line));
    }
}
