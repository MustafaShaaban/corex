<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Export;

defined('ABSPATH') || exit;

use RuntimeException;

/**
 * The working file of an export in progress (spec 103).
 *
 * An export is gathered a batch at a time, over several requests, and its file is written once,
 * at the end. Between the two, the batches are kept here, a record to a line. Nothing but the
 * batch in hand is in memory.
 */
final readonly class ExportSpool
{
    public function __construct(private string $path)
    {
    }

    /**
     * @param list<array<string,mixed>> $records
     */
    public function append(array $records): void
    {
        $lines = '';
        foreach ($records as $record) {
            $lines .= wp_json_encode($record) . "\n";
        }
        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- appending to a private working file, batch after batch.
        if (file_put_contents($this->path, $lines, FILE_APPEND | LOCK_EX) === false) {
            throw new RuntimeException('CoreX could not write the export working file.');
        }
    }

    /**
     * Every record gathered so far. Can be read more than once; each read goes back to the disk.
     *
     * @return iterable<array<string,mixed>>
     */
    public function records(): iterable
    {
        if (! is_file($this->path)) {
            return;
        }
        $stream = fopen($this->path, 'rb');
        if ($stream === false) {
            throw new RuntimeException('CoreX could not read the export working file.');
        }

        try {
            while (($line = fgets($stream)) !== false) {
                $record = json_decode($line, true);
                if (is_array($record)) {
                    yield $record;
                }
            }
        } finally {
            fclose($stream);
        }
    }

    public function discard(): void
    {
        if (is_file($this->path)) {
            wp_delete_file($this->path);
        }
    }
}
