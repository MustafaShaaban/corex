<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Session;

defined('ABSPATH') || exit;

use Corex\Profile\Account\AccountResult;
use WP_Session_Tokens;

/**
 * Thin boundary over WordPress session tokens. Lists a user's active sessions (via the
 * pure {@see SessionList}) and lets them end other or all sessions using core's own
 * helpers — the front-office equivalent of "sign out everywhere". Destroying sessions
 * never removes the acting session unless "all" is requested by that user.
 */
final class SessionService
{
    /**
     * @return list<array{current:bool,login:int,expiration:int,ip:string,ua:string}>
     */
    public function active(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $manager = WP_Session_Tokens::get_instance($userId);
        $current = function_exists('wp_get_session_token') ? (string) wp_get_session_token() : '';

        return SessionList::format($manager->get_all(), $this->verifier($current));
    }

    public function signOutOthers(int $userId): AccountResult
    {
        if ($userId <= 0 || $userId !== get_current_user_id()) {
            return AccountResult::fail('forbidden', __('You can only manage your own sessions.', 'corex'));
        }

        wp_destroy_other_sessions();

        return AccountResult::ok('sessions_cleared', __('Signed out of all other sessions.', 'corex'), $userId);
    }

    public function signOutAll(int $userId): AccountResult
    {
        if ($userId <= 0 || $userId !== get_current_user_id()) {
            return AccountResult::fail('forbidden', __('You can only manage your own sessions.', 'corex'));
        }

        WP_Session_Tokens::get_instance($userId)->destroy_all();

        return AccountResult::ok('sessions_ended', __('Signed out of every session.', 'corex'), $userId);
    }

    /**
     * Hash a raw token to its stored verifier form (WordPress stores the SHA-256 hash).
     */
    private function verifier(string $token): string
    {
        return $token === '' ? '' : hash('sha256', $token);
    }
}
