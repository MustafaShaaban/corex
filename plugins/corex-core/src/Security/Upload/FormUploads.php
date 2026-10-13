<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * Says whether an attachment is a file a visitor sent through a form's file field.
 *
 * For a site's own listener that does something with the file: attaches it to an email, sends it
 * to another system. A listener is handed the number the file was stored under, and before it
 * opens the file at that number it asks here.
 *
 *     $cv = $event->values['cv'] ?? null;
 *     if (is_int($cv) && FormUploads::cameThrough('cv', $cv)) {
 *         $path = get_attached_file($cv);
 *     }
 *
 * The question is about the field's name, and not about which form: a file is recorded with the
 * field it came through. How it is recorded is the framework's to change, which is why a site
 * asks this and does not read the attachment's meta.
 *
 * It is here, beside the store, and not with the forms: the forms plugin writes the label, and
 * the inbox, which reads it, does not depend on that plugin.
 */
final class FormUploads
{
    /** What a form's upload is recorded as having come through, before the field's name. */
    private const CONTEXT = 'form-';

    /** The label a file sent through this field is stored under. */
    public static function contextFor(string $field): string
    {
        return self::CONTEXT . $field;
    }

    /** Whether the attachment is a file the store keeps for a form field of this name. */
    public static function cameThrough(string $field, int $attachmentId): bool
    {
        return StoredUpload::contextOf($attachmentId) === sanitize_key(self::contextFor($field));
    }

    /** Whether the attachment is a file the store keeps for a form, whatever the field. */
    public static function holds(int $attachmentId): bool
    {
        return str_starts_with((string) StoredUpload::contextOf($attachmentId), self::CONTEXT);
    }
}
