<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Session;

defined('ABSPATH') || exit;

/**
 * Pure formatter for a user's active login sessions. Turns the session records WordPress
 * hands over into a display-ready list, newest sign-in first, and marks the one the request
 * was made with. WordPress gives the records as a plain list, with no token or verifier in
 * it, so the request's own session is known by its record and not by a key (DECISIONS #349).
 */
final class SessionList
{
    /**
     * @param array<array-key, array<string, mixed>> $records What `WP_Session_Tokens::get_all()`
     *        returns: one record per session, in no order that means anything.
     * @param array<string, mixed>|null $current What `WP_Session_Tokens::get()` returns for the
     *        request's own session; null for a request that has none.
     *
     * @return list<array{current:bool,login:int,expiration:int,ip:string,ua:string}>
     */
    public static function format(array $records, ?array $current = null): array
    {
        $sessions = [];
        // Two sign-ins in one second from one address and browser hold the same record. One row
        // is marked, never two: they read the same, and one of them is the request's.
        $found = false;

        foreach ($records as $record) {
            $isCurrent = ! $found && $record === $current;
            $found     = $found || $isCurrent;

            $sessions[] = [
                'current'    => $isCurrent,
                'login'      => (int) ($record['login'] ?? 0),
                'expiration' => (int) ($record['expiration'] ?? 0),
                'ip'         => (string) ($record['ip'] ?? ''),
                'ua'         => (string) ($record['ua'] ?? ''),
            ];
        }

        usort($sessions, static fn (array $a, array $b): int => $b['login'] <=> $a['login']);

        return $sessions;
    }
}
