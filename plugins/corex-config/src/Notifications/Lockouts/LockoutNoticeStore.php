<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Notifications\Lockouts;

defined('ABSPATH') || exit;

/**
 * The lockout notices the release before spec 124 stored, and their rewrite (FR-020).
 */
interface LockoutNoticeStore
{
    /**
     * Lockout notices not yet written to the new rule, with an id above `$afterId`, lowest id first.
     *
     * @return list<StoredLockoutNotice>
     */
    public function legacyAfter(int $afterId, int $limit): array;

    /** Replaces the notice's words, merge key, source and details with the new rule's. */
    public function rewrite(int $id, LoginLockoutNotice $notice): void;
}
