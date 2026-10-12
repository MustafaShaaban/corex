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

    /**
     * Writes a reply down again, in place of itself: it was sent another time (spec 106,
     * FR-022).
     *
     * @param SubmissionReplyRecord $was The reply as it was read. Somebody else may have sent
     *                                   it again in the meantime, and their sending must not
     *                                   be written over.
     *
     * @throws \DomainException When the reply is not there as it was read, or could not be written.
     */
    public function replace(int $submissionId, SubmissionReplyRecord $was, SubmissionReplyRecord $now): void;

    /** @return list<SubmissionReplyRecord> Oldest first. */
    public function forSubmission(int $submissionId): array;
}
