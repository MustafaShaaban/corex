<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * What {@see AttachmentStore} records on an attachment it keeps, and how that is read back.
 *
 * The two keys were string literals in the store and in three classes that read them. They are
 * named here so that the writer and the readers cannot come to disagree, and they are not a
 * contract: code outside the framework asks {@see contextOf()}, or for a form's upload
 * {@see FormUploads}, and does not read the meta.
 */
final class StoredUpload
{
    /** Post meta: `'1'` on an attachment the store keeps in the protected directory. */
    public const MARKER = '_corex_protected';

    /** Post meta: the label the caller stored the file under. Absent when it gave none. */
    public const CONTEXT = '_corex_upload_context';

    /**
     * The label an upload was stored under.
     *
     * @return string|null Null when the attachment is not one the store keeps. An empty string
     *                     when it is, and was stored with no label.
     */
    public static function contextOf(int $attachmentId): ?string
    {
        if (
            $attachmentId <= 0
            || get_post_type($attachmentId) !== 'attachment'
            || get_post_meta($attachmentId, self::MARKER, true) !== '1'
        ) {
            return null;
        }

        return (string) get_post_meta($attachmentId, self::CONTEXT, true);
    }
}
