<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

/**
 * What a failed access-request submission has to carry across the redirect (spec 079, FR-011).
 *
 * Only failures need this. The success path is read back out of the database, so refreshing,
 * bookmarking or returning tomorrow all show the same truthful state and nothing can be faked by
 * editing the URL.
 *
 * The stored value is user-bound (the key contains the user ID), short-lived, and read once —
 * `take()` deletes before returning, so a second tab cannot show the first tab's error and a
 * back-button revisit shows the plain form rather than a stale complaint.
 *
 * The reason travels here rather than in the query string because it is the requester's own words,
 * and a query string is logged by the web server, kept in browser history, and forwarded in
 * `Referer` headers.
 */
final class AccessRequestFlash
{
    private const PREFIX = 'corex_access_request_flash_';
    private const TTL    = 300;

    public function store(int $userId, string $problem, string $reason, string $reference = ''): void
    {
        if ($userId < 1) {
            return;
        }

        set_transient(self::PREFIX . $userId, [
            'problem'   => $problem,
            'reason'    => mb_substr($reason, 0, 2000),
            'reference' => $reference,
        ], self::TTL);
    }

    /** @return array{problem:string,reason:string,reference:string}|null */
    public function take(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        $stored = get_transient(self::PREFIX . $userId);
        delete_transient(self::PREFIX . $userId);

        if (! is_array($stored) || ! isset($stored['problem'])) {
            return null;
        }

        return [
            'problem'   => (string) $stored['problem'],
            'reason'    => (string) ($stored['reason'] ?? ''),
            'reference' => (string) ($stored['reference'] ?? ''),
        ];
    }
}
