<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Keeps a submission's replies as its post meta, one row to a reply.
 *
 * With the submission, so that what already carries a submission carries its replies: the
 * trash, a restore, a permanent delete (spec 105). One row to a reply and not one list in one
 * row, so that two people answering at the same moment each add theirs and neither writes over
 * the other's.
 */
final class WpSubmissionReplyStore implements SubmissionReplyStore
{
    public const META_KEY = 'corex_submission_replies';

    public function add(int $submissionId, SubmissionReplyRecord $reply): void
    {
        if ($submissionId < 1 || get_post_type($submissionId) !== 'corex_submission') {
            throw new DomainException(__('Submission was not found.', 'corex'));
        }
        // WordPress strips a level of slashes on the way in; a reply that mentions a path keeps it.
        if (add_post_meta($submissionId, self::META_KEY, wp_slash($reply->toArray())) === false) {
            throw new DomainException(__('The reply could not be kept with its submission.', 'corex'));
        }
    }

    public function replace(int $submissionId, SubmissionReplyRecord $was, SubmissionReplyRecord $now): void
    {
        // The row as the database holds it is what WordPress matches the old value by. A reply
        // kept by an earlier release has fewer fields than one read back, so it is not `$was`.
        foreach ((array) get_post_meta($submissionId, self::META_KEY, false) as $stored) {
            $kept = is_array($stored) ? SubmissionReplyRecord::fromArray($stored) : null;
            if ($kept === null || $kept->id !== $was->id) {
                continue;
            }
            if ($kept->toArray() === $was->toArray()
                && update_post_meta($submissionId, self::META_KEY, wp_slash($now->toArray()), $stored) !== false) {
                return;
            }

            break;
        }

        throw new DomainException(__('The reply could not be kept with its submission.', 'corex'));
    }

    public function forSubmission(int $submissionId): array
    {
        $replies = [];
        foreach ((array) get_post_meta($submissionId, self::META_KEY, false) as $stored) {
            $reply = is_array($stored) ? SubmissionReplyRecord::fromArray($stored) : null;
            if ($reply !== null) {
                $replies[] = $reply;
            }
        }
        usort(
            $replies,
            static fn (SubmissionReplyRecord $a, SubmissionReplyRecord $b): int => strcmp($a->createdAt, $b->createdAt),
        );

        return $replies;
    }
}
