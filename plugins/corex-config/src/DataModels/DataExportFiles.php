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
 *
 * Each model of an export is gathered into a working file of its own and becomes a sheet of the
 * one document (spec 109, slice 2): a workbook's sheet, a file in a CSV archive, a section of a
 * PDF, which is what the writers already make of a document with several sheets.
 */
final readonly class DataExportFiles
{
    public function __construct(
        private DataExportTable $table,
        private DataExportAbout $about,
        private ExportWriters $writers,
        private ExportDirectory $directory,
    ) {
    }

    /**
     * @param int                       $index   Which of the run's parts these records are of.
     * @param list<array<string,mixed>> $records
     */
    public function collect(DataExportRun $run, int $index, array $records): void
    {
        // Only what was asked for is written down, to the working file as to the export: a
        // record's other fields have no business on the disk.
        $asked = array_flip($run->parts[$index]->columns);

        $this->spool($run, $index)->append(array_map(
            static fn (array $record): array => array_intersect_key($record, $asked),
            $records,
        ));
    }

    /**
     * @param list<array{index:int,label:string,fields:list<DataField>}> $models The parts to
     *        write, in order: which part, the source's name (it names the sheet), and every
     *        field the source declares.
     */
    public function finish(DataExportRun $run, array $models): ExportFile
    {
        $sheets = [];
        foreach ($models as $model) {
            $sheets[] = $this->table->sheet(
                $model['label'],
                $model['fields'],
                $run->parts[$model['index']]->columns,
                $this->spool($run, $model['index'])->records(),
            );
        }
        $file = $this->writers
            ->for($run->format, $run->separator)
            ->write(
                new ExportDocument($this->title($models), $sheets, $this->about->facts($run, $models)),
                $this->target($run),
            );

        // Every part's, a part that was left out half way among them.
        foreach (array_keys($run->parts) as $index) {
            $this->spool($run, $index)->discard();
        }

        return $file;
    }

    /** @param list<array{index:int,label:string,fields:list<DataField>}> $models */
    private function title(array $models): string
    {
        return count($models) === 1 ? $models[0]['label'] : __('Data export', 'corex');
    }

    private function spool(DataExportRun $run, int $index): ExportSpool
    {
        // The first part keeps the name a run of one part always had, so an export that was
        // being written when a site took this release still finds its working file.
        $part = $index === 0 ? '' : '-' . $index;

        return new ExportSpool($this->directory->path() . '/data-run-' . $run->id . $part . '.ndjson');
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
