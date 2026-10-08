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
}
