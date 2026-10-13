<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Session;

defined('ABSPATH') || exit;

use Corex\Profile\Account\AccountResult;
use Corex\Profile\Support\SiteDate;
use WP_Session_Tokens;

/**
 * Thin boundary over WordPress session tokens. Lists a user's active sessions (via the
 * pure {@see SessionList}) and lets them end other or all sessions using core's own
 * helpers — the front-office equivalent of "sign out everywhere". Destroying sessions
 * never removes the acting session unless "all" is requested by that user.
 *
 * A session of the list also carries what the account block prints for it: `device`, the
 * browser and system its user agent names, and `signedIn`, when it signed in as the site
 * writes a date (spec 068, FR-161g).
 */
final class SessionService
{
    public function __construct(private readonly SiteDate $dates)
    {
    }

    /**
     * @return list<array{current:bool,login:int,expiration:int,ip:string,ua:string,device:string,signedIn:string}>
     */
    public function active(int $userId): array
    {
        if ($userId <= 0) {
            return [];
        }

        $manager = WP_Session_Tokens::get_instance($userId);

        return array_map(
            $this->forItsOwner(...),
            SessionList::format($manager->get_all(), $this->ownSession($manager)),
        );
    }

    /**
     * @param array{current:bool,login:int,expiration:int,ip:string,ua:string} $session
     *
     * @return array{current:bool,login:int,expiration:int,ip:string,ua:string,device:string,signedIn:string}
     */
    private function forItsOwner(array $session): array
    {
        return $session + [
            'device'   => DeviceName::fromUserAgent($session['ua']),
            'signedIn' => $this->signedIn($session['login']),
        ];
    }

    /**
     * A session WordPress recorded no sign-in time for says nothing of it.
     */
    private function signedIn(int $login): string
    {
        if ($login <= 0) {
            return '';
        }

        /* translators: %s: a date and a time, such as "October 13, 2026 at 4:23 pm". */
        return sprintf(__('Signed in %s', 'corex'), $this->dates->dateAndTime($login));
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
