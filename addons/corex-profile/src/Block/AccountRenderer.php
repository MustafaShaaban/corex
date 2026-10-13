<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Block;

defined('ABSPATH') || exit;

use Corex\Profile\Reset\ResetState;

/**
 * Pure renderer for the front-office account block. Given a request context it returns
 * the correct panel — the login/register/recovery forms for a guest, or the profile,
 * sessions, and notifications panels for a signed-in user. All dynamic values are
 * escaped; every form posts to the `corex/v1/account/*` REST routes via `account.js`,
 * with the REST nonce the block carries in `data-nonce`. No WordPress state is read
 * here, so the panel is unit-testable headlessly.
 *
 * What the server answers is written under the button that asked: each form, and the
 * session buttons, have a message line of their own (spec 068, FR-161a).
 *
 * Somebody who arrives from a reset email with a key WordPress takes is shown the
 * new-password form and nothing else, whoever is signed in on that browser (FR-158b).
 *
 * A field's id holds the block it is in and its form, so no two fields of a page share
 * one and a label names the field printed beside it (spec 068, FR-161b).
 *
 * A field also says what it is for, in the words a browser and a password manager read
 * (spec 068, FR-161c).
 */
final class AccountRenderer
{
    /**
     * What each field is for, by form and field name: its `autocomplete` value, one of the
     * HTML specification's autofill field names. Sign-in asks for the password the account
     * has and registration for one it does not have yet, which is how a password manager
     * tells them apart. The display name is the name a member is shown by, so it is a
     * `nickname` and not their full `name`.
     *
     * Registration's own username is left out on purpose: Chromium fills a field marked
     * `username` with the person's saved email address, and an address is not always a
     * username the form accepts (DECISIONS #342).
     */
    private const PURPOSES = [
        'login'         => ['login' => 'username', 'password' => 'current-password'],
        'register'      => [
            'email'            => 'email',
            'password'         => 'new-password',
            'password_confirm' => 'new-password',
        ],
        'reset-request' => ['login' => 'username'],
        'profile'       => [
            'display_name' => 'nickname',
            'first_name'   => 'given-name',
            'last_name'    => 'family-name',
            'email'        => 'email',
        ],
    ];

    /**
     * `instance` is what the ids of this block's fields start with. A page that holds the
     * block more than once hands each one its own; without one they start `corex-account`.
     *
     * @param array{loggedIn:bool,displayName?:string,firstName?:string,lastName?:string,email?:string,registrationOpen?:bool,logoutUrl?:string,nonce?:string,instance?:string,pageMark?:string,reset?:ResetState} $ctx
     */
    public function render(array $ctx): string
    {
        $nonce = (string) ($ctx['nonce'] ?? '');
        $block = (string) ($ctx['instance'] ?? 'corex-account');
        $attrs = sprintf(
            ' class="corex-account" data-corex-account data-nonce="%s"',
            esc_attr($nonce),
        );
        $reset = $ctx['reset'] ?? ResetState::none();

        $body = match (true) {
            $reset->step === ResetState::CHOOSE => $this->resetForm($reset, $block),
            (bool) ($ctx['loggedIn'] ?? false)  => $this->member($ctx, $block),
            default                             => $this->guest($ctx, $block),
        };

        return '<div' . $attrs . '>' . $this->arrivalNote($reset) . $body . '</div>';
    }

    /**
     * What a reset says on a page that has just been loaded, above the forms: nobody pressed
     * a button for it to be written under. It is a message line like the others, so
     * `account.js` empties it when the next answer is written.
     */
    private function arrivalNote(ResetState $reset): string
    {
        [$state, $message] = match ($reset->step) {
            ResetState::EXPIRED => ['error', __('This password reset link is invalid or has expired. Please ask for a new one.', 'corex')],
            ResetState::DONE    => ['ok', __('Your password has been updated. You can now sign in.', 'corex')],
            default             => ['', ''],
        };

        return $message === ''
            ? ''
            : '<p class="corex-account__status" role="status" aria-live="polite" data-state="' . esc_attr($state) . '">'
                . esc_html($message) . '</p>';
    }

    /**
     * The key and the login travel with the form: `account/reset` asks for both, and
     * WordPress checks them again when the password is saved.
     */
    private function resetForm(ResetState $reset, string $block): string
    {
        $field = $this->fieldsOf($block, 'reset');

        return '<div class="corex-account__grid">'
            . '<form class="corex-account__form" data-corex-account-form="reset" method="post" novalidate>'
            . '<h2 class="corex-account__title">' . esc_html__('Choose a new password', 'corex') . '</h2>'
            . $this->hidden('login', $reset->login)
            . $this->hidden('key', $reset->key)
            . $field('password', __('New password', 'corex'), 'password', true)
            . $field('password_confirm', __('Confirm new password', 'corex'), 'password', true)
            . $this->submit(__('Save password', 'corex'))
            . '</form>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function guest(array $ctx, string $block): string
    {
        $registration = (bool) ($ctx['registrationOpen'] ?? false)
            ? $this->registerForm($block)
            : '<p class="corex-account__note">' . esc_html__('Registration is currently closed.', 'corex') . '</p>';

        return '<div class="corex-account__grid">'
            . $this->loginForm($block)
            . $registration
            . $this->forgotForm($block, (string) ($ctx['pageMark'] ?? ''))
            . '</div>';
    }

    private function loginForm(string $block): string
    {
        $field = $this->fieldsOf($block, 'login');

        return '<form class="corex-account__form" data-corex-account-form="login" method="post" novalidate>'
            . '<h2 class="corex-account__title">' . esc_html__('Sign in', 'corex') . '</h2>'
            . $field('login', __('Username or email', 'corex'), 'text', true)
            . $field('password', __('Password', 'corex'), 'password', true)
            . '<label class="corex-account__check"><input type="checkbox" name="remember" value="1" /> '
            . esc_html__('Keep me signed in', 'corex') . '</label>'
            . $this->submit(__('Sign in', 'corex'))
            . '</form>';
    }

    private function registerForm(string $block): string
    {
        $field = $this->fieldsOf($block, 'register');

        return '<form class="corex-account__form" data-corex-account-form="register" method="post" novalidate>'
            . '<h2 class="corex-account__title">' . esc_html__('Create an account', 'corex') . '</h2>'
            . $this->honeypot()
            . $field('email', __('Email', 'corex'), 'email', true)
            . $field('username', __('Username', 'corex'), 'text', false)
            . $field('password', __('Password', 'corex'), 'password', true)
            . $field('password_confirm', __('Confirm password', 'corex'), 'password', true)
            . '<label class="corex-account__check"><input type="checkbox" name="consent" value="1" required /> '
            . esc_html__('I agree to the terms and privacy policy.', 'corex') . '</label>'
            . $this->submit(__('Create account', 'corex'))
            . '</form>';
    }

    /**
     * @param string $pageMark The page the block is on, signed: the email's link comes back
     *                         to it. Empty where the block is on no page, and the email then
     *                         links to WordPress's own screen.
     */
    private function forgotForm(string $block, string $pageMark): string
    {
        $field = $this->fieldsOf($block, 'reset-request');

        return '<form class="corex-account__form" data-corex-account-form="reset-request" method="post" novalidate>'
            . '<h2 class="corex-account__title">' . esc_html__('Forgot your password?', 'corex') . '</h2>'
            . '<p class="corex-account__note">' . esc_html__('Enter your username or email and we’ll send a reset link.', 'corex') . '</p>'
            . ($pageMark !== '' ? $this->hidden('page', $pageMark) : '')
            . $field('login', __('Username or email', 'corex'), 'text', true)
            . $this->submit(__('Email a reset link', 'corex'))
            . '</form>';
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function member(array $ctx, string $block): string
    {
        $name = esc_html((string) ($ctx['displayName'] ?? ''));
        $logout = esc_url((string) ($ctx['logoutUrl'] ?? ''));

        return '<div class="corex-account__member">'
            . '<div class="corex-account__memberhead">'
            . '<h2 class="corex-account__title">' . esc_html__('Your account', 'corex') . '</h2>'
            . '<p class="corex-account__hello">' . sprintf(
                /* translators: %s: display name. */
                esc_html__('Signed in as %s', 'corex'),
                $name,
            ) . '</p>'
            . '<a class="corex-account__logout" href="' . $logout . '">' . esc_html__('Sign out', 'corex') . '</a>'
            . '</div>'
            . $this->profileForm($ctx, $block)
            . $this->sessionsPanel()
            . $this->notificationsPanel()
            . '</div>';
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function profileForm(array $ctx, string $block): string
    {
        $field = $this->fieldsOf($block, 'profile');

        return '<form class="corex-account__form" data-corex-account-form="profile" method="post" novalidate>'
            . '<h3 class="corex-account__subtitle">' . esc_html__('Profile', 'corex') . '</h3>'
            . $field('display_name', __('Display name', 'corex'), 'text', true, (string) ($ctx['displayName'] ?? ''))
            . $field('first_name', __('First name', 'corex'), 'text', false, (string) ($ctx['firstName'] ?? ''))
            . $field('last_name', __('Last name', 'corex'), 'text', false, (string) ($ctx['lastName'] ?? ''))
            . $field('email', __('Email', 'corex'), 'email', true, (string) ($ctx['email'] ?? ''))
            . $this->submit(__('Save profile', 'corex'))
            . '</form>';
    }

    private function sessionsPanel(): string
    {
        return '<section class="corex-account__sessions" data-corex-account-sessions>'
            . '<h3 class="corex-account__subtitle">' . esc_html__('Active sessions', 'corex') . '</h3>'
            . '<ul class="corex-account__session-list" data-corex-account-session-list></ul>'
            . '<div class="corex-account__actions">'
            . '<button type="button" class="corex-account__btn" data-corex-account-action="revoke-others">'
            . esc_html__('Sign out other sessions', 'corex') . '</button>'
            . '<button type="button" class="corex-account__btn" data-corex-account-action="revoke-all">'
            . esc_html__('Sign out everywhere', 'corex') . '</button>'
            . '</div>'
            . $this->messageLine()
            . '</section>';
    }

    private function notificationsPanel(): string
    {
        return '<section class="corex-account__notifications" data-corex-account-notifications>'
            . '<h3 class="corex-account__subtitle">' . esc_html__('Recent activity', 'corex') . '</h3>'
            . '<ul class="corex-account__notification-list" data-corex-account-notification-list></ul>'
            . '</section>';
    }

    /**
     * What prints the labelled fields of one form. Their ids start with the block and the
     * form: two forms of one panel ask for a password, and each label has to name its own
     * field. What a field is for is looked up in PURPOSES; a field that is not there says
     * nothing about itself.
     *
     * @return \Closure(string $name, string $label, string $type, bool $required, string $value=): string
     */
    private function fieldsOf(string $block, string $form): \Closure
    {
        return static function (string $name, string $label, string $type, bool $required, string $value = '') use ($block, $form): string {
            $id      = $block . '-' . $form . '-' . $name;
            $purpose = self::PURPOSES[$form][$name] ?? null;

            return '<p class="corex-account__row">'
                . '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>'
                . '<input type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"'
                . ($purpose !== null ? ' autocomplete="' . esc_attr($purpose) . '"' : '')
                . ' value="' . esc_attr($value) . '"' . ($required ? ' required' : '') . ' />'
                . '</p>';
        };
    }

    private function hidden(string $name, string $value): string
    {
        return '<input type="hidden" name="' . esc_attr($name) . '" value="' . esc_attr($value) . '" />';
    }

    private function honeypot(): string
    {
        // Off-screen honeypot; real users never fill it, bots do (checked server-side).
        return '<p class="corex-account__hp" aria-hidden="true">'
            . '<label>' . esc_html__('Leave this field empty', 'corex')
            . '<input type="text" name="corex_hp" tabindex="-1" autocomplete="off" /></label></p>';
    }

    private function submit(string $label): string
    {
        return '<button type="submit" class="corex-account__submit">' . esc_html($label) . '</button>'
            . $this->messageLine();
    }

    /**
     * The line `account.js` writes an answer into. It is printed empty and not hidden, so
     * it is in the accessibility tree before its text changes; the stylesheet gives an
     * empty line no box.
     */
    private function messageLine(): string
    {
        return '<p class="corex-account__status" role="status" aria-live="polite"></p>';
    }
}
