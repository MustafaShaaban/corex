<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Config\Export\ExportDocument;

/**
 * Makes the document an export is written from, out of what it gathered (spec 103): a sheet for
 * each form, with that form's questions as its columns, under a title and what the export was.
 */
final readonly class SubmissionExportDocuments
{
    public function __construct(
        private SubmissionExportTable $table,
        private SubmissionQuestions $questions,
        private SubmissionExportAbout $about,
    ) {
    }

    public function for(SubmissionExportRun $run, SubmissionExportSpool $spool): ExportDocument
    {
        $forms  = $spool->forms();
        $sheets = [];
        foreach ($forms as $slug => $name) {
            $sheets[] = $this->table->sheet($name, $spool->of($slug), $this->questions->for($slug), $run->columns);
        }

        return new ExportDocument(
            $this->about->title($forms),
            // Every submission the export was to hold went out of reach while it ran. The file
            // still has its headings, so what was asked for is plain from it.
            $sheets ?: [$this->table->sheet(SubmissionExportFiles::SEVERAL_FORMS, [], [], $run->columns)],
            $this->about->facts($run, $forms),
        );
    }
}
