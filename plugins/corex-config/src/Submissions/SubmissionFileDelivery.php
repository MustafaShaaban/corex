<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Security\Upload\AttachmentDelivery;
use DomainException;

/**
 * The address a file uploaded with a submission is opened at (spec 081, FR-009).
 *
 * The link names the submission and the field, not the attachment, because the rule is the
 * submission's: whoever the inbox lets read that submission may open its file, and nobody else.
 * {@see AttachmentDelivery} asks WordPress whether the person may read a private attachment,
 * which editors and administrators may and a role that was only given the inbox may not. That
 * person would have been shown a link that refused them.
 *
 * The rule is asked on every request, of {@see SubmissionQueryService}. This class reads the
 * request and gives the answer its status; the file itself leaves through `AttachmentDelivery`,
 * with the headers decided there.
 */
final class SubmissionFileDelivery
{
    /** The `admin-post.php` action this answers. The provider hooks it, and makes this when it fires. */
    public const ACTION = 'corex_submission_file';

    public function __construct(
        private readonly SubmissionQueryService $submissions,
        private readonly AttachmentDelivery $delivery,
    ) {
    }

    /**
     * The address of the file one submission holds as its answer to one field. It is for the
     * person signed in when it is made: it stops working when their session ends, and within a
     * day in any case, as a WordPress nonce does.
     */
    public static function url(int $submissionId, string $field): string
    {
        return add_query_arg(
            [
                'action' => self::ACTION,
                'submission' => $submissionId,
                'field' => rawurlencode($field),
                '_wpnonce' => wp_create_nonce(self::nonceFor($submissionId, $field)),
            ],
            admin_url('admin-post.php'),
        );
    }

    public function serve(): void
    {
        // phpcs:disable WordPress.Security.NonceVerification.Recommended -- verified immediately below.
        $submissionId = isset($_GET['submission']) ? absint(wp_unslash($_GET['submission'])) : 0;
        $field        = isset($_GET['field']) ? sanitize_text_field(wp_unslash($_GET['field'])) : '';
        $nonce        = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        // phpcs:enable WordPress.Security.NonceVerification.Recommended

        if ($submissionId <= 0 || $field === '' || ! wp_verify_nonce($nonce, self::nonceFor($submissionId, $field))) {
            wp_die(esc_html__('That link is no longer valid.', 'corex'), 403);
        }

        // Signed out is a different answer from not permitted: one is put right by signing in.
        if (! is_user_logged_in()) {
            wp_die(esc_html__('Sign in to open this file.', 'corex'), 401);
        }

        try {
            $file = $this->submissions->file(get_current_user_id(), $submissionId, $field);
        } catch (DomainException) {
            // Signed in, and not somebody who manages submissions at all.
            wp_die(esc_html__('You don’t have permission to open this file.', 'corex'), 403);
        }

        // A submission this person's scope leaves out answers as one that is not there.
        if ($file === null) {
            wp_die(esc_html__('That file is not available here.', 'corex'), 404);
        }
        if ($file['missing']) {
            wp_die(esc_html__('That file is missing.', 'corex'), 404);
        }

        $this->delivery->deliver($file['id']);
    }

    private static function nonceFor(int $submissionId, string $field): string
    {
        return self::ACTION . '_' . $submissionId . '_' . $field;
    }
}
