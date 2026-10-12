<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * The emails a site told CoreX it sent a submission's sender itself: the confirmation a
 * site's own listener sends, which no flow recorded (spec 106, FR-042).
 */
interface SubmissionConversationStore
{
    /**
     * @return list<array{message_id:string,subject:string}> In the order they were added.
     */
    public function emailsOf(int $submissionId): array;

    /**
     * Records an email for a submission, by the id it is sent under. Nothing happens where
     * there is no such submission, or the email is already recorded.
     */
    public function add(int $submissionId, string $messageId, string $subject): void;
}
