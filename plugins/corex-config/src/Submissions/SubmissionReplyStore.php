<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Where the replies sent to a submission's sender are kept, with the submission.
 */
interface SubmissionReplyStore
{
    /**
     * @throws \DomainException When the submission is not there, or the reply could not be written.
     */
    public function add(int $submissionId, SubmissionReplyRecord $reply): void;

    /** @return list<SubmissionReplyRecord> Oldest first. */
    public function forSubmission(int $submissionId): array;
}
