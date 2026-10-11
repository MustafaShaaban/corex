<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * The files visitors uploaded with their submissions, as the site holds them.
 *
 * A submission stores the id of an attachment where a file was uploaded (spec 081). This is asked
 * what that id is, so nothing that reads a submission reaches into how a file is kept.
 */
interface SubmissionFiles
{
    /**
     * The file uploaded through one field of a form.
     *
     * @param string $field        The field's key, as the submission stores its answers.
     * @param int    $attachmentId What the submission holds under that key.
     *
     * @return array{id:int,name:string,missing:bool}|null Null when the id is not a file uploaded
     *                                                     through a field of that name. `missing`
     *                                                     when its record is there and the file
     *                                                     is not.
     */
    public function uploaded(string $field, int $attachmentId): ?array;

    /**
     * The address at which a person allowed to read a submission opens the file of one answer.
     */
    public function address(int $submissionId, string $field): string;
}
