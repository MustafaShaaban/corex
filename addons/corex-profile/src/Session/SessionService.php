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

        return SessionList::format($manager->get_all(), $this->ownSession($manager));
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
     * The record of the session this request was made with, as the manager holds it. Null for
     * a request with no session cookie (an application password, WP-CLI) and for a cookie that
     * is somebody else's or has run out. The token goes to WordPress and nowhere else.
     *
     * @return array<string, mixed>|null
     */
    private function ownSession(WP_Session_Tokens $manager): ?array
    {
        $record = $manager->get(wp_get_session_token());

        return is_array($record) ? $record : null;
    }
}
