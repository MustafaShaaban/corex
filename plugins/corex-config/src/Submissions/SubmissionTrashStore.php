<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

/**
 * Where a submission is put in the trash and taken out of it (spec 105).
 */
interface SubmissionTrashStore
{
    /** Trashed by somebody in the inbox. */
    public const VIA_INBOX = 'inbox';

    /**
     * @param string $via What trashed it: one of the `VIA_*` names.
     *
     * @throws \DomainException When there is no such submission in the inbox.
     */
    public function trash(int $id, int $actorId, string $via): void;

    /**
     * Put a trashed submission back as it was.
     *
     * @throws \DomainException When there is no such submission in the trash.
     */
    public function restore(int $id): void;

    /**
     * A trashed submission, shaped as the inbox shapes one, or null when it is not in the trash.
     *
     * @return array<string,mixed>|null
     */
    public function findTrashed(int $id): ?array;

    /**
     * The files that were uploaded with a submission, as attachment ids.
     *
     * @return list<int>
     */
    public function uploadsOf(int $id): array;

    /**
     * Remove a file that was uploaded with a submission, from disk and from the media records.
     * False when it could not be removed, or is not such a file.
     */
    public function forgetUpload(int $attachmentId): bool;

    /**
     * The ids of the email attempts made for a submission: its notifications, and replies to it.
     *
     * @return list<string>
     */
    public function emailAttemptsOf(int $id): array;

    /**
     * Delete a trashed submission for good, with everything stored on it. What is stored
     * elsewhere and tied to it is the caller's to remove first.
     *
     * @throws \DomainException  When there is no such submission in the trash.
     * @throws \RuntimeException When it could not be deleted.
     */
    public function delete(int $id): void;
}
