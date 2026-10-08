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

    public function uploadsOf(int $id): array
    {
        $uploads = [];
        foreach ((array) get_post_meta($id) as $key => $values) {
            // An uploaded file's answer is the id of its attachment.
            $value = str_starts_with((string) $key, 'corex_field_') ? ($values[0] ?? '') : '';
            if (is_numeric($value) && $this->isFormUpload((int) $value)) {
                $uploads[] = (int) $value;
            }
        }

        return array_values(array_unique($uploads));
    }

    public function forgetUpload(int $attachmentId): bool
    {
        // Only what a visitor uploaded through a form: never an attachment an answer merely
        // happens to share a number with.
        return $this->isFormUpload($attachmentId) && wp_delete_attachment($attachmentId, true) instanceof \WP_Post;
    }

    public function emailAttemptsOf(int $id): array
    {
        $emails = (array) get_post_meta($id, 'corex_email_json', true);
        $delivery = (array) get_post_meta($id, 'corex_notification_delivery', true);
        $history = (array) get_post_meta($id, 'corex_submission_timeline', true);

        $attempts = [
            $delivery['attempt_id'] ?? '',
            ...array_column(array_filter((array) ($emails['bindings'] ?? []), 'is_array'), 'attempt_id'),
            ...array_map(
                static fn (array $entry): mixed => $entry['summary']['attempt_id'] ?? '',
                array_filter($history, static fn (mixed $entry): bool => is_array($entry) && is_array($entry['summary'] ?? null)),
            ),
        ];

        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $attempt): string => is_string($attempt) ? $attempt : '', $attempts),
        )));
    }

    public function trashedBefore(\DateTimeImmutable $cutoff, int $limit): array
    {
        // The date is written by trash() in one form and one time zone, so it sorts as text.
        return $this->trashedIds($limit, [
            'key' => 'corex_trashed_at',
            'value' => $cutoff->setTimezone(new \DateTimeZone('UTC'))->format(DATE_ATOM),
            'compare' => '<',
        ]);
    }

    public function adoptWordPressTrash(int $limit): int
    {
        $ids = $this->trashedIds($limit, ['key' => '_wp_trash_meta_time', 'compare' => 'EXISTS'], [
            'key' => 'corex_trashed_at',
            'compare' => 'NOT EXISTS',
        ]);
        foreach ($ids as $id) {
            update_post_meta($id, 'corex_trashed_at', gmdate(DATE_ATOM, (int) get_post_meta($id, '_wp_trash_meta_time', true)));
            update_post_meta($id, 'corex_trashed_via', self::VIA_WORDPRESS);
            foreach (self::WORDPRESS_RECORD as $key) {
                delete_post_meta($id, $key);
            }
        }

        return count($ids);
    }

    /**
     * @param array<string,mixed> ...$clauses
     *
     * @return list<int>
     */
    private function trashedIds(int $limit, array ...$clauses): array
    {
        $found = new \WP_Query([
            'post_type' => self::POST_TYPE,
            'post_status' => 'trash',
            'posts_per_page' => max(1, $limit),
            'orderby' => 'ID',
            'order' => 'ASC',
            'fields' => 'ids',
            'no_found_rows' => true,
            'update_post_term_cache' => false,
            'meta_query' => ['relation' => 'AND', ...$clauses],
        ]);

        return array_map('intval', $found->posts);
    }

    public function delete(int $id): void
    {
        if ($this->statusOf($id) !== 'trash') {
            throw new DomainException(__('Submission was not found in the trash.', 'corex'));
        }
        if (! wp_delete_post($id, true) instanceof \WP_Post) {
            throw new RuntimeException(__('The submission could not be deleted.', 'corex'));
        }
    }

    /** Whether an attachment is a file a visitor uploaded through a form, kept in protected uploads. */
    private function isFormUpload(int $attachmentId): bool
    {
        return get_post_type($attachmentId) === 'attachment'
            && get_post_meta($attachmentId, '_corex_protected', true) === '1'
            && str_starts_with((string) get_post_meta($attachmentId, '_corex_upload_context', true), 'form-');
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
