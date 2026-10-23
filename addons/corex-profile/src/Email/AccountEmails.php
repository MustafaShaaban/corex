<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Email;

defined('ABSPATH') || exit;

use Corex\Mail\DesignedEmails;
use Corex\Profile\Reset\ResetLink;
use WP_Error;
use WP_User;

/**
 * The account emails drawn in Email Studio (spec 120, story 6): the password reset and the
 * welcome, in the one frame, linking to the account page and never to the login screen, whose
 * address may be hidden.
 *
 * Each is sent in the place of WordPress's own while a request from the account block is
 * answered, and only where its template is active and draws. Otherwise WordPress sends its own,
 * as it always has, and CoreX Mail records that the template could not be used.
 */
final class AccountEmails
{
    public function __construct(
        private readonly ResetLink $links,
        private readonly ?DesignedEmails $designed = null,
    ) {
    }

    /**
     * Runs a request for a reset, with the designed email sent in the place of WordPress's.
     *
     * @template T
     *
     * @param int           $pageId  A published page with the account block, or zero for none.
     * @param callable(): T $request What asks WordPress for the email.
     *
     * @return T
     */
    public function resetDuring(int $pageId, callable $request): mixed
    {
        if ($pageId <= 0 || $this->designed === null) {
            return $request();
        }

        $instead = fn (mixed $send, mixed $login, mixed $user): bool => (bool) $send
            && ! ($user instanceof WP_User && $this->sentReset($pageId, $user));
        add_filter('send_retrieve_password_email', $instead, 10, 3);

        try {
            return $request();
        } finally {
            remove_filter('send_retrieve_password_email', $instead, 10);
        }
    }

    /**
     * Runs a registration, with the designed welcome sent in the place of WordPress's
     * set-password email: the member chose a password a moment ago.
     *
     * @template T
     *
     * @param int           $pageId   The page the form was on, or zero.
     * @param callable(): T $register What creates the account.
     *
     * @return T
     */
    public function welcomeDuring(int $pageId, callable $register): mixed
    {
        if ($this->designed === null) {
            return $register();
        }

        $instead = fn (mixed $send, mixed $user): bool => (bool) $send
            && ! ($user instanceof WP_User && $this->sentWelcome($pageId, $user));
        add_filter('wp_send_new_user_notification_to_user', $instead, 10, 2);

        try {
            return $register();
        } finally {
            remove_filter('wp_send_new_user_notification_to_user', $instead, 10);
        }
    }

    private function sentReset(int $pageId, WP_User $user): bool
    {
        $key = get_password_reset_key($user);
        if ($key instanceof WP_Error) {
            return false;
        }

        $sent = $this->designed?->send(AccountEmailTemplates::RESET, [$user->user_email], [
            'user'    => ['name' => $user->display_name, 'email' => $user->user_email],
            'action'  => ['url' => $this->links->emailed($pageId, $user->user_login, $key)],
            'account' => ['url' => (string) get_permalink($pageId)],
            'reset'   => [
                'asked'   => wp_date((string) get_option('date_format') . ' ' . (string) get_option('time_format')),
                'expires' => self::howLong((int) apply_filters('password_reset_expiration', DAY_IN_SECONDS)),
            ],
        ], __('Account: password reset', 'corex'));

        return $sent !== null;
    }

    private function sentWelcome(int $pageId, WP_User $user): bool
    {
        $page = $pageId > 0 ? (string) get_permalink($pageId) : '';
        $sent = $this->designed?->send(AccountEmailTemplates::WELCOME, [$user->user_email], [
            'user'    => ['name' => $user->display_name, 'email' => $user->user_email],
            'account' => ['url' => $page !== '' ? $page : (string) home_url('/')],
        ], __('Account: welcome', 'corex'));

        return $sent !== null;
    }

    /** How long a reset link works, in words: "1 day", "2 hours". */
    private static function howLong(int $seconds): string
    {
        return human_time_diff(0, max(60, $seconds));
    }
}
