<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportDirectory;
use Corex\Config\Export\ExportDocument;
use Corex\Config\Export\ExportFile;
use Corex\Config\Export\ExportSpool;
use Corex\Config\Export\ExportWriters;
use Corex\Data\DataField;

/**
 * Gathers a Data export's records batch by batch, and writes its file when the last has arrived
 * (spec 103, US10).
 *
 * The file is written by the writers the Submissions export uses, into the directory it uses. It
 * was a string in post meta, read, added to and written back whole on every batch.
 */
final readonly class DataExportFiles
{
    public function __construct(
        private DataExportTable $table,
        private ExportWriters $writers,
        private ExportDirectory $directory,
    ) {
    }

    /**
     * @param list<array<string,mixed>> $records
     */
    public function collect(DataExportRun $run, array $records): void
    {
        // Only what was asked for is written down, to the working file as to the export: a
        // record's other fields have no business on the disk.
        $asked = array_flip($run->columns);

        $this->spool($run)->append(array_map(
            static fn (array $record): array => array_intersect_key($record, $asked),
            $records,
        ));
    }

    /**
     * @param string          $label  The source's name, which names the sheet.
     * @param list<DataField> $fields Every field the source declares.
     */
    public function finish(DataExportRun $run, string $label, array $fields): ExportFile
    {
        $spool = $this->spool($run);
        $file  = $this->writers
            ->for($run->format, $run->separator)
            ->write(
                new ExportDocument($label, [$this->table->sheet($label, $fields, $run->columns, $spool->records())]),
                $this->target($run),
            );

        $spool->discard();

        return $file;
    }

    private function spool(DataExportRun $run): ExportSpool
    {
        return new ExportSpool($this->directory->path() . '/data-run-' . $run->id . '.ndjson');
    }

    /**
     * Named so that it cannot be guessed: the directory is told not to serve files, and not every
     * server listens.
     */
    private function target(DataExportRun $run): string
    {
        return sprintf('%s/data-export-%d-%s', $this->directory->path(), $run->id, bin2hex(random_bytes(12)));
    }
}
