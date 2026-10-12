<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Keeps a draft as the submission's post meta, under a key of the person's own.
 *
 * With the submission and not with the person: a draft quotes and names the customer it
 * answers, so it goes where the submission goes. It is in the trash with it, comes back with
 * it, and is deleted with it by WordPress itself; anonymizing removes it by this prefix. Kept
 * with the person it would have outlived all of that.
 *
 * A key to a person, so that two people writing to the same customer at once each have their
 * own and neither writes over the other's.
 */
final class WpSubmissionReplyDraftStore implements SubmissionReplyDraftStore
{
    public const META_PREFIX = 'corex_reply_draft_';

    public function find(int $submissionId, int $userId): ?SubmissionReplyDraft
    {
        if (! $this->isSubmission($submissionId) || $userId < 1) {
            return null;
        }

        return SubmissionReplyDraft::fromStored(get_post_meta($submissionId, self::keyOf($userId), true));
    }

    public function keep(int $submissionId, int $userId, SubmissionReplyDraft $draft): void
    {
        if (! $this->isSubmission($submissionId) || $userId < 1) {
            throw new DomainException(__('Submission was not found.', 'corex'));
        }
        $stored = $draft->toArray();
        // WordPress answers false both when a write failed and when what is there is what was
        // written, so the second is asked first. It strips a level of slashes on the way in.
        if (get_post_meta($submissionId, self::keyOf($userId), true) === $stored) {
            return;
        }
        if (update_post_meta($submissionId, self::keyOf($userId), wp_slash($stored)) === false) {
            throw new DomainException(__('The draft could not be kept.', 'corex'));
        }
    }

    public function discard(int $submissionId, int $userId): void
    {
        if ($this->isSubmission($submissionId) && $userId > 0) {
            delete_post_meta($submissionId, self::keyOf($userId));
        }
    }

    public function discardAllOf(int $userId): void
    {
        if ($userId > 0) {
            delete_post_meta_by_key(self::keyOf($userId));
        }
    }

    public static function keyOf(int $userId): string
    {
        return self::META_PREFIX . $userId;
    }

    private function isSubmission(int $submissionId): bool
    {
        return $submissionId > 0 && get_post_type($submissionId) === 'corex_submission';
    }
}
