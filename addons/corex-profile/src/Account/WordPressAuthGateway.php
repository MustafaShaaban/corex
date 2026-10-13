<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Account;

defined('ABSPATH') || exit;

use WP_Error;
use WP_Session_Tokens;
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
    /** What WordPress holds a login in; it refuses a longer one. */
    private const MAX_LOGIN_LENGTH = 60;

    /** How many logins are tried for one address before its owner is asked for a username. */
    private const LOGINS_TRIED = 20;

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

    public function acceptsUsername(string $username): bool
    {
        // wp_insert_user() does not refuse a login, it stores sanitize_user( $login, true ) of it.
        // validate_username() is core's own answer to "would that be the same login".
        return validate_username($username);
    }

    public function loginForAddress(string $email): string
    {
        if (mb_strlen($email) <= self::MAX_LOGIN_LENGTH) {
            return $email;
        }

        // What WordPress would keep of the part before the @: it stores a login without the
        // characters a login cannot have, and an address may have several of them.
        $kept = sanitize_user(PublicName::forLogin($email), true);

        for ($number = 1; $kept !== '' && $number <= self::LOGINS_TRIED; $number++) {
            $suffix = $number === 1 ? '' : '-' . $number;
            $login  = substr($kept, 0, self::MAX_LOGIN_LENGTH - strlen($suffix)) . $suffix;

            if (! $this->usernameExists($login)) {
                return $login;
            }
        }

        return '';
    }

    public function createUser(string $email, string $login, string $password, string $name): AccountResult
    {
        $userId = wp_insert_user([
            'user_email'    => $email,
            'user_login'    => $login,
            'user_pass'     => $this->asWordPressTakesIt($password),
            'role'          => get_option('default_role', 'subscriber'),
        ] + $this->namesFor($name));

        if ($userId instanceof WP_Error) {
            return AccountResult::fail('register_failed', $userId->get_error_message());
        }

        // WordPress hands back 0, and no error, for an account its database did not take.
        if ($userId < 1) {
            return AccountResult::fail('register_failed', __('The account could not be created. Please try again.', 'corex'));
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
     * of the account's comments. They are made from the name the account goes by, which the
     * service makes with {@see PublicName} so that none of them shows an address.
     *
     * @return array{display_name: string, nickname: string, user_nicename?: string}
     */
    private function namesFor(string $name): array
    {
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
        $forWordPress = $this->asWordPressTakesIt($password);
        $heldAsTyped  = $forWordPress !== $password && $this->holdsPasswordAsTyped($login, $password);

        // One sign-in, with the form the account holds: each one that fails is counted
        // against the person, so the other form is not tried as a second.
        $user = wp_signon([
            'user_login'    => $login,
            'user_password' => $heldAsTyped ? $password : $forWordPress,
            'remember'      => $remember,
        ], is_ssl());

        if ($user instanceof WP_Error) {
            // Deliberately generic: do not reveal which of user/password was wrong.
            return AccountResult::fail('login_failed', __('Those credentials did not match. Please try again.', 'corex'));
        }

        if ($heldAsTyped) {
            $this->keepPasswordAsWordPressDoes($user, $forWordPress, $remember);
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

        reset_password($user, $this->asWordPressTakesIt($password));

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

    /**
     * A password as WordPress's own screens hand it over. They hash and check the text of a
     * form, which still has the request's slashes on it; the password of a REST request has
     * none. Hashed as it arrived here, a password with a quote or a backslash in it was another
     * password at `wp-login.php` (DECISIONS #334).
     */
    private function asWordPressTakesIt(string $password): string
    {
        return wp_slash($password);
    }

    /**
     * Until #334 this gateway hashed a password as it was typed. Whether the account a sign-in
     * is for still holds that hash: the one with that username or the one with that address,
     * which are the two WordPress tries.
     *
     * Nothing is changed here and nothing is counted: it only decides which form the sign-in
     * is made with.
     */
    private function holdsPasswordAsTyped(string $login, string $password): bool
    {
        $candidates = [get_user_by('login', $login)];
        if (is_email($login)) {
            $candidates[] = get_user_by('email', $login);
        }

        foreach ($candidates as $user) {
            if ($user instanceof WP_User && wp_check_password($password, $user->user_pass, $user->ID)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Moves an account that has just signed in with the hash of its password as typed to the
     * hash a WordPress screen would have stored, so that `wp-login.php` accepts it too.
     *
     * A browser's cookie is made from the stored hash: the one `wp_signon()` has just set is
     * void, and so is every other browser's. Their sessions are ended, so that the account's
     * list of sessions shows only ones that work, and this browser is given a cookie again.
     */
    private function keepPasswordAsWordPressDoes(WP_User $user, string $forWordPress, bool $remember): void
    {
        wp_set_password($forWordPress, $user->ID);
        WP_Session_Tokens::get_instance($user->ID)->destroy_all();
        wp_set_auth_cookie($user->ID, $remember, is_ssl());
    }
}
