<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Block;

defined('ABSPATH') || exit;

/**
 * Pure renderer for the front-office account block. Given a request context it returns
 * the correct panel — the login/register/recovery forms for a guest, or the profile,
 * sessions, and notifications panels for a signed-in user. All dynamic values are
 * escaped; every form posts to the `corex/v1/account/*` REST routes via `account.js`
 * (progressive enhancement — the register/reset forms also carry a real nonce). No
 * WordPress state is read here, so the panel is unit-testable headlessly.
 */
final class AccountRenderer
{
    /**
     * @param array{loggedIn:bool,displayName?:string,firstName?:string,lastName?:string,email?:string,registrationOpen?:bool,logoutUrl?:string,nonce?:string} $ctx
     */
    public function render(array $ctx): string
    {
        $nonce = (string) ($ctx['nonce'] ?? '');
        $attrs = sprintf(
            ' class="corex-account" data-corex-account data-nonce="%s"',
            esc_attr($nonce),
        );

        $body = ($ctx['loggedIn'] ?? false)
            ? $this->member($ctx)
            : $this->guest($ctx);

        return '<div' . $attrs . '>'
            . '<p class="corex-account__status" role="status" aria-live="polite" hidden></p>'
            . $body
            . '</div>';
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function guest(array $ctx): string
    {
        $registration = (bool) ($ctx['registrationOpen'] ?? false)
            ? $this->registerForm()
            : '<p class="corex-account__note">' . esc_html__('Registration is currently closed.', 'corex') . '</p>';

        return '<div class="corex-account__grid">'
            . $this->loginForm()
            . $registration
            . $this->forgotForm()
            . '</div>';
    }

    private function loginForm(): string
    {
        return '<form class="corex-account__form" data-corex-account-form="login" method="post" novalidate>'
            . '<h2 class="corex-account__title">' . esc_html__('Sign in', 'corex') . '</h2>'
            . $this->field('login', __('Username or email', 'corex'), 'text', true)
            . $this->field('password', __('Password', 'corex'), 'password', true)
            . '<label class="corex-account__check"><input type="checkbox" name="remember" value="1" /> '
            . esc_html__('Keep me signed in', 'corex') . '</label>'
            . $this->submit(__('Sign in', 'corex'))
            . '</form>';
    }

    private function registerForm(): string
    {
        return '<form class="corex-account__form" data-corex-account-form="register" method="post" novalidate>'
            . '<h2 class="corex-account__title">' . esc_html__('Create an account', 'corex') . '</h2>'
            . $this->honeypot()
            . $this->field('email', __('Email', 'corex'), 'email', true)
            . $this->field('username', __('Username', 'corex'), 'text', false)
            . $this->field('password', __('Password', 'corex'), 'password', true)
            . $this->field('password_confirm', __('Confirm password', 'corex'), 'password', true)
            . '<label class="corex-account__check"><input type="checkbox" name="consent" value="1" required /> '
            . esc_html__('I agree to the terms and privacy policy.', 'corex') . '</label>'
            . $this->submit(__('Create account', 'corex'))
            . '</form>';
    }

    private function forgotForm(): string
    {
        return '<form class="corex-account__form" data-corex-account-form="reset-request" method="post" novalidate>'
            . '<h2 class="corex-account__title">' . esc_html__('Forgot your password?', 'corex') . '</h2>'
            . '<p class="corex-account__note">' . esc_html__('Enter your username or email and we’ll send a reset link.', 'corex') . '</p>'
            . $this->field('login', __('Username or email', 'corex'), 'text', true)
            . $this->submit(__('Email a reset link', 'corex'))
            . '</form>';
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function member(array $ctx): string
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
            . $this->profileForm($ctx)
            . $this->sessionsPanel()
            . $this->notificationsPanel()
            . '</div>';
    }

    /**
     * @param array<string, mixed> $ctx
     */
    private function profileForm(array $ctx): string
    {
        return '<form class="corex-account__form" data-corex-account-form="profile" method="post" novalidate>'
            . '<h3 class="corex-account__subtitle">' . esc_html__('Profile', 'corex') . '</h3>'
            . $this->field('display_name', __('Display name', 'corex'), 'text', true, (string) ($ctx['displayName'] ?? ''))
            . $this->field('first_name', __('First name', 'corex'), 'text', false, (string) ($ctx['firstName'] ?? ''))
            . $this->field('last_name', __('Last name', 'corex'), 'text', false, (string) ($ctx['lastName'] ?? ''))
            . $this->field('email', __('Email', 'corex'), 'email', true, (string) ($ctx['email'] ?? ''))
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
            . '</div></section>';
    }

    private function notificationsPanel(): string
    {
        return '<section class="corex-account__notifications" data-corex-account-notifications>'
            . '<h3 class="corex-account__subtitle">' . esc_html__('Recent activity', 'corex') . '</h3>'
            . '<ul class="corex-account__notification-list" data-corex-account-notification-list></ul>'
            . '</section>';
    }

    private function field(string $name, string $label, string $type, bool $required, string $value = ''): string
    {
        $id = 'corex-account-' . $name;

        return '<p class="corex-account__row">'
            . '<label for="' . esc_attr($id) . '">' . esc_html($label) . '</label>'
            . '<input type="' . esc_attr($type) . '" id="' . esc_attr($id) . '" name="' . esc_attr($name) . '"'
            . ' value="' . esc_attr($value) . '"' . ($required ? ' required' : '') . ' />'
            . '</p>';
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
        return '<button type="submit" class="corex-account__submit">' . esc_html($label) . '</button>';
    }
}
