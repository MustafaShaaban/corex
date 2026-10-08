<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * What the mail system keeps about emails sent for a submission, and how it is told to let go.
 *
 * When a submission is deleted for good, the copies of its emails go with it (spec 105, FR-011).
 * The Submissions inbox does not know how email is stored, and the email add-on is optional: it
 * fills this when it is there, and without it there is nothing of its to remove.
 */
interface SubmissionEmailRecords
{
    /**
     * Remove what is kept for these attempts: the record of each attempt, and any captured copy
     * of the message. An id nothing is kept for is passed over.
     *
     * @param list<string> $attemptIds
     */
    public function forget(array $attemptIds): void;
}
