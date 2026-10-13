<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Account;

defined('ABSPATH') || exit;

/**
 * Front-office account orchestration. Owns validation and policy (registration gate,
 * email/password rules, generic reset responses that resist user enumeration) and
 * delegates every WordPress-touching step to an injected {@see AuthGateway}. Thin
 * controllers/REST call this; the theme only presents its results (Principles I/III).
 */
final class AccountService
{
    public const MIN_PASSWORD_LENGTH = 8;

    /**
     * What an account's address is held in. WordPress does not refuse a longer one: it stores
     * nothing and reports no error, so the address is refused here, where a reason can be given.
     */
    public const MAX_EMAIL_LENGTH = 100;

    public function __construct(private readonly AuthGateway $gateway)
    {
    }

    public function register(RegistrationRequest $request): AccountResult
    {
        if (! $this->gateway->registrationOpen()) {
            return AccountResult::fail('registration_closed', __('Registration is currently closed.', 'corex'));
        }

        $emailError = $this->emailProblem($request->email);
        if ($emailError !== null) {
            return $emailError;
        }

        if (! $request->consent) {
            return AccountResult::fail('consent_required', __('Please accept the terms to create an account.', 'corex'));
        }

        $passwordError = $this->passwordProblem($request->password, $request->passwordConfirm);
        if ($passwordError !== null) {
            return $passwordError;
        }

        $usernameError = $this->usernameProblem($request->username);
        if ($usernameError !== null) {
            return $usernameError;
        }

        if ($this->gateway->emailExists($request->email)) {
            return AccountResult::fail('email_taken', __('An account with that email already exists.', 'corex'));
        }

        $login = $this->loginFor($request);
        if ($login === '') {
            return AccountResult::fail(
                'username_required',
                __('A username could not be made from that email address. Please choose a username.', 'corex'),
            );
        }

        if ($this->gateway->usernameExists($login)) {
            return AccountResult::fail('username_taken', __('That username is taken. Please choose another.', 'corex'));
        }

        return $this->gateway->createUser($request->email, $login, $request->password, $this->nameFor($request));
    }

    /**
     * With no username the gateway says what the login is, and the person signs in with the
     * address whatever it is.
     */
    private function loginFor(RegistrationRequest $request): string
    {
        return $request->username !== '' ? $request->username : $this->gateway->loginForAddress($request->email);
    }

    /**
     * What the account goes by is made from what was typed, not from the login: a login made
     * for an address is nobody's choice.
     */
    private function nameFor(RegistrationRequest $request): string
    {
        return PublicName::forLogin($request->username !== '' ? $request->username : $request->email);
    }

    public function login(string $login, string $password, bool $remember): AccountResult
    {
        if (trim($login) === '' || $password === '') {
            return AccountResult::fail('empty_credentials', __('Please enter your username and password.', 'corex'));
        }

        return $this->gateway->authenticate($login, $password, $remember);
    }

    /**
     * Always reports the same generic outcome whether or not the account exists, so a
     * caller cannot enumerate registered users through this endpoint.
     */
    public function requestPasswordReset(string $login): AccountResult
    {
        $generic = AccountResult::ok(
            'reset_requested',
            __('If an account matches, a password reset link is on its way.', 'corex'),
        );

        if (trim($login) === '') {
            return AccountResult::fail('empty_login', __('Please enter your username or email.', 'corex'));
        }

        // Fire the real request but never leak whether it resolved to a user.
        $this->gateway->requestPasswordReset($login);

        return $generic;
    }

    public function resetPassword(string $key, string $login, string $password, string $passwordConfirm): AccountResult
    {
        if (trim($key) === '' || trim($login) === '') {
            return AccountResult::fail('invalid_reset', __('This password reset link is invalid or has expired.', 'corex'));
        }

        $passwordError = $this->passwordProblem($password, $passwordConfirm);
        if ($passwordError !== null) {
            return $passwordError;
        }

        return $this->gateway->resetPassword($key, $login, $password);
    }

    /**
     * @param array<string, string> $fields display_name, first_name, last_name, email
     */
    public function updateProfile(int $userId, array $fields): AccountResult
    {
        if ($userId <= 0 || $userId !== $this->gateway->currentUserId()) {
            return AccountResult::fail('forbidden', __('You can only edit your own profile.', 'corex'));
        }

        $emailError = isset($fields['email']) ? $this->emailProblem($fields['email']) : null;
        if ($emailError !== null) {
            return $emailError;
        }

        if (isset($fields['display_name']) && trim($fields['display_name']) === '') {
            return AccountResult::fail('empty_display_name', __('Display name cannot be empty.', 'corex'));
        }

        return $this->gateway->updateProfile($userId, $fields);
    }

    private function emailProblem(string $email): ?AccountResult
    {
        if (! $this->isValidEmail($email)) {
            return AccountResult::fail('invalid_email', __('Please enter a valid email address.', 'corex'));
        }

        if (strlen($email) > self::MAX_EMAIL_LENGTH) {
            return AccountResult::fail(
                'email_too_long',
                sprintf(
                    /* translators: %d: the most characters an account's email address can have. */
                    __('That email address is too long for an account here. Please use one of %d characters or fewer.', 'corex'),
                    self::MAX_EMAIL_LENGTH,
                ),
            );
        }

        return null;
    }

    /**
     * Only a username somebody typed is judged: with none, the login is made from the address,
     * and the person signs in with the address.
     */
    private function usernameProblem(string $username): ?AccountResult
    {
        if ($username === '' || $this->gateway->acceptsUsername($username)) {
            return null;
        }

        return AccountResult::fail(
            'invalid_username',
            __('A username can have letters without accents, numbers, spaces and the characters _ . - @ and no others. Please choose another.', 'corex'),
        );
    }

    private function passwordProblem(string $password, string $confirm): ?AccountResult
    {
        if (strlen($password) < self::MIN_PASSWORD_LENGTH) {
            return AccountResult::fail(
                'weak_password',
                sprintf(
                    /* translators: %d: minimum password length. */
                    __('Password must be at least %d characters.', 'corex'),
                    self::MIN_PASSWORD_LENGTH,
                ),
            );
        }

        if ($password !== $confirm) {
            return AccountResult::fail('password_mismatch', __('The passwords do not match.', 'corex'));
        }

        return null;
    }

    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
}
