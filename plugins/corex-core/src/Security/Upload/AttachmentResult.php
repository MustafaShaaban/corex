<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Upload;

defined('ABSPATH') || exit;

/**
 * The outcome of storing an uploaded file: an attachment id, or a machine-readable reason.
 *
 * Separate from {@see UploadResult} because they answer different questions. `UploadResult` says
 * whether a descriptor is worth acting on; this says whether the bytes made it to disk and what to
 * refer to them by. Collapsing them would give the caller an `$attachmentId` that is meaningless in
 * half the cases it is present.
 */
final class AttachmentResult
{
    private function __construct(
        public readonly bool $stored,
        public readonly int $attachmentId,
        public readonly string $reason,
    ) {
    }

    public static function stored(int $attachmentId): self
    {
        return new self(true, $attachmentId, '');
    }

    public static function refused(string $reason): self
    {
        return new self(false, 0, $reason);
    }
}
