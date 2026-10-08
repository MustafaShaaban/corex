<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use DomainException;
use RuntimeException;

/**
 * The trash, as WordPress stores it: a submission's post is `trash` (spec 105, plan D1).
 *
 * Every read of submissions asks for `private` posts, so a trashed one leaves the inbox, its
 * counts, the exports and the Data screen at once, with no query to change.
 *
 * It is not trashed with `wp_trash_post()`. That deletes on the spot when `EMPTY_TRASH_DAYS` is
 * 0, and writes `_wp_trash_meta_time`, the one thing WordPress's daily `wp_scheduled_delete()`
 * selects by: a submission trashed that way is deleted for good 30 days later, with nothing said
 * and anything uploaded with it left on disk. CoreX writes its own record instead, and without
 * that meta WordPress's clean-up never finds the post.
 */
final readonly class WpSubmissionTrashStore implements SubmissionTrashStore
{
    private const POST_TYPE = 'corex_submission';

    /** What CoreX records of a trashing. */
    private const RECORD = ['corex_trashed_at', 'corex_trashed_by', 'corex_trashed_via'];

    /** What `wp_trash_post()` left on a submission trashed before this, which a restore clears. */
    private const WORDPRESS_RECORD = ['_wp_trash_meta_status', '_wp_trash_meta_time', '_wp_desired_post_slug'];

    public function __construct(private SubmissionInboxReader $reader)
    {
    }

    public function trash(int $id, int $actorId, string $via): void
    {
        if ($this->statusOf($id) !== 'private') {
            throw new DomainException(__('Submission was not found.', 'corex'));
        }

        $this->setStatus($id, 'trash');
        update_post_meta($id, 'corex_trashed_at', gmdate(DATE_ATOM));
        update_post_meta($id, 'corex_trashed_by', $actorId);
        update_post_meta($id, 'corex_trashed_via', $via);
        update_post_meta($id, 'corex_submission_updated_at', gmdate(DATE_ATOM));
    }

    public function restore(int $id): void
    {
        if ($this->statusOf($id) !== 'trash') {
            throw new DomainException(__('Submission was not found in the trash.', 'corex'));
        }

        $this->setStatus($id, 'private');
        foreach ([...self::RECORD, ...self::WORDPRESS_RECORD] as $key) {
            delete_post_meta($id, $key);
        }
        update_post_meta($id, 'corex_submission_updated_at', gmdate(DATE_ATOM));
    }

    public function findTrashed(int $id): ?array
    {
        // Read as somebody who may see everything: whether this person may is the caller's question.
        $record = $this->reader->findInbox($id, new SubmissionAccessScope(PHP_INT_MAX, true, [], [], true));

        return $record !== null && ($record['trashed'] ?? false) === true ? $record : null;
    }

    /** The post's status, or '' when it is not a submission. */
    private function statusOf(int $id): string
    {
        return get_post_type($id) === self::POST_TYPE ? (string) get_post_status($id) : '';
    }

    private function setStatus(int $id, string $status): void
    {
        $saved = wp_update_post(['ID' => $id, 'post_status' => $status], true);
        if (is_wp_error($saved)) {
            throw new RuntimeException($saved->get_error_message());
        }
    }
}
