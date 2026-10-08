<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportDirectory;
use Corex\Config\Export\ExportFile;
use Corex\Config\Export\ExportWriters;

/**
 * Gathers an export's submissions batch by batch, and writes its file when the last has arrived.
 *
 * Each form in the export becomes a sheet of its own, with that form's questions as its columns.
 */
final readonly class SubmissionExportFiles
{
    /** What an export of more than one form is called. */
    public const SEVERAL_FORMS = 'submissions';

    public function __construct(
        private SubmissionExportDocuments $documents,
        private ExportWriters $writers,
        private ExportDirectory $directory,
    ) {
    }

    /**
     * @param list<array<string,mixed>> $records
     */
    public function collect(SubmissionExportRun $run, array $records): void
    {
        $this->spool($run)->append($records);
    }

    /**
     * @return array{file:ExportFile,subject:string} The written file, and the form it holds or
     *                                               {@see self::SEVERAL_FORMS}.
     */
    public function finish(SubmissionExportRun $run): array
    {
        $spool = $this->spool($run);
        $forms = $spool->forms();
        $file  = $this->writers
            ->for($run->format, $run->separator)
            ->write($this->documents->for($run, $spool), $this->target($run));

        $spool->discard();

        return ['file' => $file, 'subject' => count($forms) === 1 ? (string) array_key_first($forms) : self::SEVERAL_FORMS];
    }

    private function spool(SubmissionExportRun $run): SubmissionExportSpool
    {
        return new SubmissionExportSpool($this->directory->path() . '/run-' . $run->id . '.ndjson');
    }

    /**
     * Named so that it cannot be guessed: the directory is told not to serve files, and not every
     * server listens.
     */
    private function target(SubmissionExportRun $run): string
    {
        return sprintf('%s/export-%d-%s', $this->directory->path(), $run->id, bin2hex(random_bytes(12)));
    }
}
