<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Account;

defined('ABSPATH') || exit;

use WP_Error;
use WP_User;

/**
 * The WordPress-backed {@see AuthGateway}. Every call delegates to a core primitive —
 * `wp_signon`, `wp_insert_user`, `retrieve_password`, the reset-key flow, and
 * `wp_update_user` — so Corex never reimplements authentication or password hashing
 * and inherits core's login filters (including any rate-limiting). It returns typed
 * {@see AccountResult}s and never exposes passwords, reset keys, or tokens.
 */
final class WordPressAuthGateway implements AuthGateway
{
    public function registrationOpen(): bool
    {
        return (bool) get_option('users_can_register', false);
    }

    public function emailExists(string $email): bool
    {
        // email_exists()/username_exists() return the user ID when present and a
        // falsy value (false or null, depending on WP version) when not — so test
        // truthiness rather than a specific sentinel.
        return (bool) email_exists($email);
    }

    public function usernameExists(string $login): bool
    {
        return (bool) username_exists($login);
    }

    public function createUser(string $email, string $login, string $password): AccountResult
    {
        $userId = wp_insert_user([
            'user_email'    => $email,
            'user_login'    => $login,
            'user_pass'     => $password,
            'role'          => get_option('default_role', 'subscriber'),
        ] + $this->namesFor($login));

        if ($userId instanceof WP_Error) {
            return AccountResult::fail('register_failed', $userId->get_error_message());
        }

        // Core new-user notifications (admin + user). Never auto-authenticate here.
        wp_new_user_notification((int) $userId, null, 'user');

        return AccountResult::ok(
            'registered',
            __('Your account has been created. You can now sign in.', 'corex'),
            (int) $userId,
        );
    }

    /**
     * The three names WordPress would otherwise make from the login: the display name, the
     * nickname and the nicename, which is the address of the author page and a class on each
     * of the account's comments. For a login that is an email address they are made from
     * {@see PublicName}, so none of them shows the address.
     *
     * @return array{display_name: string, nickname: string, user_nicename?: string}
     */
    private function namesFor(string $login): array
    {
        $name  = PublicName::forLogin($login);
        $names = ['display_name' => $name, 'nickname' => $name];

        // WordPress shortens a nicename it makes itself, and refuses one it is handed that is
        // over 50 characters or comes out empty: that one it makes from the login, as before.
        $nicename = mb_substr($name, 0, 50);
        if (sanitize_title(sanitize_user($nicename, true)) !== '') {
            $names['user_nicename'] = $nicename;
        }

        return $names;
    }

    public function authenticate(string $login, string $password, bool $remember): AccountResult
    {
        $user = wp_signon([
            'user_login'    => $login,
            'user_password' => $password,
            'remember'      => $remember,
        ], is_ssl());

        if ($user instanceof WP_Error) {
            // Deliberately generic: do not reveal which of user/password was wrong.
            return AccountResult::fail('login_failed', __('Those credentials did not match. Please try again.', 'corex'));
        }

        wp_set_current_user($user->ID);

        return AccountResult::ok(
            'logged_in',
            __('You are signed in.', 'corex'),
            (int) $user->ID,
        );
    }

    public function requestPasswordReset(string $login): AccountResult
    {
        $result = retrieve_password($login);

        if ($result instanceof WP_Error) {
            return AccountResult::fail('reset_failed', $result->get_error_message());
        }

        return AccountResult::ok('reset_sent', __('Password reset email sent.', 'corex'));
    }

    public function resetPassword(string $key, string $login, string $password): AccountResult
    {
        $user = check_password_reset_key($key, $login);

        if ($user instanceof WP_Error) {
            return AccountResult::fail('invalid_reset', __('This password reset link is invalid or has expired.', 'corex'));
        }

        reset_password($user, $password);

        return AccountResult::ok(
            'password_reset',
            __('Your password has been updated. You can now sign in.', 'corex'),
            (int) $user->ID,
        );
    }

    public function updateProfile(int $userId, array $fields): AccountResult
    {
        $update = ['ID' => $userId];

        foreach (['display_name', 'first_name', 'last_name'] as $key) {
            if (isset($fields[$key])) {
                // WordPress takes one level of slashes off a user's fields on their way in.
                $update[$key] = wp_slash(sanitize_text_field($fields[$key]));
            }
        }

        if (isset($fields['email'])) {
            $update['user_email'] = sanitize_email($fields['email']);
        }

        $result = wp_update_user($update);

        if ($result instanceof WP_Error) {
            return AccountResult::fail('update_failed', $result->get_error_message());
        }

        return AccountResult::ok('updated', __('Your profile has been saved.', 'corex'), $userId);
    }

    public function currentUserId(): int
    {
        return get_current_user_id();
    }
}
