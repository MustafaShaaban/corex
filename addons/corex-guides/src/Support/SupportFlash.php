<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

/**
 * What the support form's outcome has to carry across the POST/redirect/GET (spec 087, FR-007).
 *
 * User-bound (the key holds the user ID), short-lived, and read once — {@see take()} deletes before
 * returning, so a second tab cannot show the first tab's result and a back-button visit shows the
 * plain form rather than a stale confirmation.
 *
 * The message travels here rather than in the query string because it is the person's own words,
 * and a query string is written to the server log, kept in browser history, and forwarded in
 * `Referer` headers.
 */
final class SupportFlash
{
    public const SENT          = 'sent';
    public const FAILED        = 'failed';
    public const THROTTLED     = 'throttled';
    public const EMPTY_MESSAGE = 'empty';
    public const UNAVAILABLE   = 'unavailable';

    private const PREFIX = 'corex_guides_support_flash_';
    private const TTL    = 300;

    /** Long enough to give back what somebody wrote; short enough not to be a store. */
    private const MAX_MESSAGE = 4000;

    public function store(int $userId, string $outcome, string $message = ''): void
    {
        if ($userId < 1) {
            return;
        }

        set_transient(self::PREFIX . $userId, [
            'outcome' => $outcome,
            'message' => mb_substr($message, 0, self::MAX_MESSAGE),
        ], self::TTL);
    }

    /** @return array{outcome:string,message:string}|null */
    public function take(int $userId): ?array
    {
        if ($userId < 1) {
            return null;
        }

        $stored = get_transient(self::PREFIX . $userId);
        delete_transient(self::PREFIX . $userId);

        if (! is_array($stored) || ! isset($stored['outcome'])) {
            return null;
        }

        return [
            'outcome' => (string) $stored['outcome'],
            'message' => (string) ($stored['message'] ?? ''),
        ];
    }
}
