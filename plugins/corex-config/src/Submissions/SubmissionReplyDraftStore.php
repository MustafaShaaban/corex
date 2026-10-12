<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Where a reply somebody has begun is kept until it is sent or discarded: one draft to a
 * person and a submission.
 */
interface SubmissionReplyDraftStore
{
    public function find(int $submissionId, int $userId): ?SubmissionReplyDraft;

    /**
     * @throws DomainException When there is no such submission, or the draft could not be written.
     */
    public function keep(int $submissionId, int $userId, SubmissionReplyDraft $draft): void;

    /** Removes that person's draft for that submission. Nothing happens where there is none. */
    public function discard(int $submissionId, int $userId): void;

    /** Removes every draft a person left, on every submission: they are no longer on the site. */
    public function discardAllOf(int $userId): void;
}
