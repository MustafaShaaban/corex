<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * The one place an uploaded file becomes something CoreX can refer to later (spec 081, FR-004).
 *
 * Before this there was no upload handling in the framework at all — a repo-wide search for
 * `wp_handle_upload` found two comments describing one and no code. `UploadValidator`'s docblock
 * claimed "the boundary store re-checks the real MIME" about a store nobody had written, and
 * `corex-careers` validated a CV and then discarded it. This is that store.
 *
 * ## The client's declared type is not evidence
 *
 * `UploadValidator` checks `$file['type']`, which the browser supplies and anybody can set. That is
 * worth doing — it rejects the honest mistakes cheaply — but it is not the check that matters. Here
 * the file's **content** is read, through WordPress's own `wp_check_filetype_and_ext()`, and a file
 * whose bytes disagree with its name is refused. A `.pdf` that is really a `.php` fails here or it
 * fails nowhere.
 *
 * ## Where the file goes
 *
 * Into {@see ProtectedUploads::DIRECTORY}, not the public uploads root, and the attachment post is
 * created with `post_status => 'private'`. It is still a real attachment, so
 * `addons/corex-media`'s WebP pipeline and everything else hooked to
 * `wp_generate_attachment_metadata` still applies — the reason this is a media-library attachment
 * rather than a bespoke table.
 */
final class AttachmentStore implements AttachmentStorage
{
    public function __construct(private readonly UploadValidator $validator)
    {
    }

    /**
     * Store one uploaded file and return its attachment id.
     *
     * @param array{name?:string,type?:string,size?:int,tmp_name?:string,error?:int} $file A `$_FILES` entry.
     * @param string                                                                 $context A short label recorded on the
     *                                                                                        attachment, so an operator can
     *                                                                                        tell where a file came from.
     */
    public function store(array $file, string $context = ''): AttachmentResult
    {
        $descriptor = $this->validator->validate($file);
        if (! $descriptor->valid) {
            return AttachmentResult::refused($descriptor->reason);
        }

        $directory = ProtectedUploads::ensure();
        if ($directory === '') {
            return AttachmentResult::refused('no_directory');
        }

        // The authoritative check: the bytes, not the browser's word for them.
        $checked = wp_check_filetype_and_ext(
            (string) ($file['tmp_name'] ?? ''),
            (string) ($file['name'] ?? ''),
        );
        if (empty($checked['type']) || empty($checked['ext'])) {
            return AttachmentResult::refused('content_type_mismatch');
        }

        $intoProtected = static function (array $uploads): array {
            $uploads['path']   = trailingslashit($uploads['basedir']) . ProtectedUploads::DIRECTORY;
            $uploads['url']    = trailingslashit($uploads['baseurl']) . ProtectedUploads::DIRECTORY;
            $uploads['subdir'] = '/' . ProtectedUploads::DIRECTORY;

            return $uploads;
        };

        add_filter('upload_dir', $intoProtected);
        // `test_form` off because the caller is a REST route, not a form post with WordPress's own
        // `action` field. Every other check `wp_handle_upload` makes is left on.
        $moved = wp_handle_upload($file, ['test_form' => false]);
        remove_filter('upload_dir', $intoProtected);

        if (! is_array($moved) || isset($moved['error']) || empty($moved['file'])) {
            return AttachmentResult::refused('move_failed');
        }

        $attachmentId = wp_insert_attachment([
            'post_mime_type' => $checked['type'],
            'post_title'     => sanitize_text_field(pathinfo((string) $moved['file'], PATHINFO_FILENAME)),
            'post_content'   => '',
            // Private, so it does not appear in the media library for every author on the site.
            'post_status'    => 'private',
        ], (string) $moved['file'], 0, true);

        if (is_wp_error($attachmentId) || (int) $attachmentId <= 0) {
            // FR-005: a refusal leaves nothing behind. Without this the bytes sit in a protected
            // directory with no record pointing at them — undeletable by any retention sweep,
            // because nothing knows they are there.
            wp_delete_file((string) $moved['file']);

            return AttachmentResult::refused('attachment_failed');
        }

        $attachmentId = (int) $attachmentId;

        require_once ABSPATH . 'wp-admin/includes/image.php';
        wp_update_attachment_metadata(
            $attachmentId,
            wp_generate_attachment_metadata($attachmentId, (string) $moved['file']),
        );

        update_post_meta($attachmentId, '_corex_protected', '1');
        if ($context !== '') {
            update_post_meta($attachmentId, '_corex_upload_context', sanitize_key($context));
        }

        return AttachmentResult::stored($attachmentId);
    }

    /**
     * Remove a stored file and its attachment.
     *
     * Only touches attachments this store created: `_corex_protected` is the marker, and without
     * the check a caller could hand this an arbitrary id and delete a site's logo.
     */
    public function forget(int $attachmentId): bool
    {
        if ($attachmentId <= 0 || get_post_meta($attachmentId, '_corex_protected', true) !== '1') {
            return false;
        }

        return wp_delete_attachment($attachmentId, true) !== false;
    }
}
