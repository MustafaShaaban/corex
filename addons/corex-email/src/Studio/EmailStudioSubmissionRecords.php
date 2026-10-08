<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Mail\SubmissionEmailRecords;

/**
 * What Email Studio keeps of the emails sent for a submission, and lets go of when the
 * submission is deleted for good (spec 105, FR-011).
 *
 * A captured copy holds the whole message: the address it went to and its text. It is kept 30
 * days by itself, which is 30 days too long for somebody who asked for their submission to be
 * removed.
 */
final readonly class EmailStudioSubmissionRecords implements SubmissionEmailRecords
{
    /** An attempt's id, as Email Studio makes them. Anything else was not made by it. */
    private const ATTEMPT_ID = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    public function __construct(private EmailAttemptRemoval $attempts)
    {
    }

    public function forget(array $attemptIds): void
    {
        foreach (array_unique($attemptIds) as $attemptId) {
            if (preg_match(self::ATTEMPT_ID, $attemptId) === 1) {
                $this->attempts->forgetAttempt($attemptId);
            }
        }
    }
}
