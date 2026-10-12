<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Keeps those emails as the submission's post meta, one row to an email, so that two recorded
 * at once are both kept. A row names an email by its id and holds its subject, which a site
 * may have written the person's name into ("Thank you, Salma"): it is removed when the
 * submission is anonymized, and goes with it when WordPress deletes it.
 */
final class WpSubmissionConversationStore implements SubmissionConversationStore
{
    public const META_KEY = 'corex_submission_conversation';

    public function emailsOf(int $submissionId): array
    {
        if (! $this->isSubmission($submissionId)) {
            return [];
        }
        $emails = [];
        foreach ((array) get_post_meta($submissionId, self::META_KEY, false) as $stored) {
            if (is_array($stored) && is_string($stored['message_id'] ?? null) && $stored['message_id'] !== '') {
                $emails[] = [
                    'message_id' => $stored['message_id'],
                    'subject' => is_string($stored['subject'] ?? null) ? $stored['subject'] : '',
                ];
            }
        }

        return $emails;
    }

    public function add(int $submissionId, string $messageId, string $subject): void
    {
        if (! $this->isSubmission($submissionId) || in_array($messageId, array_column($this->emailsOf($submissionId), 'message_id'), true)) {
            return;
        }
        // WordPress strips a level of slashes on the way in; a subject that holds one keeps it.
        add_post_meta($submissionId, self::META_KEY, wp_slash(['message_id' => $messageId, 'subject' => $subject]));
    }

    private function isSubmission(int $submissionId): bool
    {
        return $submissionId > 0 && get_post_type($submissionId) === 'corex_submission';
    }
}
