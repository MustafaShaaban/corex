<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Form uploads as WordPress holds them: private attachments in the protected uploads directory,
 * each marked by {@see \Corex\Security\Upload\AttachmentStore} with the field it came through.
 */
final class WpSubmissionFiles implements SubmissionFiles
{
    /** What a form's upload is recorded as having come through, before the field's name. */
    private const FORM_CONTEXT = 'form-';

    public function uploaded(string $field, int $attachmentId): ?array
    {
        if (! $this->cameThrough($field, $attachmentId)) {
            return null;
        }

        $path = get_attached_file($attachmentId);
        $path = is_string($path) ? $path : '';

        return [
            'id' => $attachmentId,
            'name' => basename($path),
            // Its record without its file is a link to "That file is missing." Say so here.
            'missing' => $path === '' || ! is_file($path),
        ];
    }

    public function address(int $submissionId, string $field): string
    {
        return SubmissionFileDelivery::url($submissionId, $field);
    }

    /**
     * Whether an attachment is a file a visitor uploaded through a form's field of this name.
     *
     * The field is part of the test, and not only the marker: a number typed into some other
     * field must not become a way to another submission's file.
     */
    private function cameThrough(string $field, int $attachmentId): bool
    {
        return $attachmentId > 0
            && get_post_type($attachmentId) === 'attachment'
            && get_post_meta($attachmentId, '_corex_protected', true) === '1'
            && get_post_meta($attachmentId, '_corex_upload_context', true) === sanitize_key(self::FORM_CONTEXT . $field);
    }
}
