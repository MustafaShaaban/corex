<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Account;

defined('ABSPATH') || exit;

/**
 * The seam over WordPress authentication. All WordPress-specific calls (wp_signon,
 * wp_insert_user, retrieve_password, reset flow, wp_update_user) live behind this
 * interface so {@see AccountService} stays pure and unit-testable. Implementations
 * must never expose passwords, reset keys, or session tokens in a returned result.
 */
interface AuthGateway
{
    public function registrationOpen(): bool;

    public function emailExists(string $email): bool;

    public function usernameExists(string $login): bool;

    /**
     * Whether an account would have exactly this username. A gateway that would store a
     * different one in its place answers false, so the person is asked for another.
     */
    public function acceptsUsername(string $username): bool;

    /**
     * The login of an account registered with no username. It is the address, unless an account
     * cannot have that address as its login: then it is one made from the address that no
     * account has. An empty string when none can be made, and the person is asked for a username.
     */
    public function loginForAddress(string $email): string;

    /**
     * @param string $name What the account goes by: the name its comments and posts are signed with.
     */
    public function createUser(string $email, string $login, string $password, string $name): AccountResult;

    public function authenticate(string $login, string $password, bool $remember): AccountResult;

    public function requestPasswordReset(string $login): AccountResult;

    public function resetPassword(string $key, string $login, string $password): AccountResult;

    /**
     * A display name as an account would hold it. A name is cleaned on its way in, and what is
     * kept can be longer than what was typed; whether a name fits is asked of this text.
     */
    public function displayNameAsKept(string $name): string;

    /**
     * @param array<string, string> $fields display_name, first_name, last_name, email
     */
    public function updateProfile(int $userId, array $fields): AccountResult;

    public function currentUserId(): int;
}
