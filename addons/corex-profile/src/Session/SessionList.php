<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Session;

defined('ABSPATH') || exit;

/**
 * Pure formatter for a user's active login sessions. Turns the raw WordPress session
 * token records into a safe, display-ready list — never exposing the session token or
 * verifier — and flags which entry is the current session. Sorted newest-login first.
 */
final class SessionList
{
    /**
     * @param array<int|string, array{expiration?:int,login?:int,ip?:string,ua?:string}> $raw
     *        Raw WP_Session_Tokens::get_all() records, keyed by hashed verifier.
     * @param string $currentVerifier Hashed verifier of the request's own session.
     *
     * @return list<array{current:bool,login:int,expiration:int,ip:string,ua:string}>
     */
    public static function format(array $raw, string $currentVerifier = ''): array
    {
        $sessions = [];

        foreach ($raw as $verifier => $record) {
            $sessions[] = [
                'current'    => $currentVerifier !== '' && (string) $verifier === $currentVerifier,
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
