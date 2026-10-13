<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Account;

defined('ABSPATH') || exit;

/**
 * The name a new account goes by: what a comment or a post of theirs is signed with.
 *
 * A login is that name, as it is in WordPress, unless the login is an email address. An account
 * registered with no username has its address as its login, and an address is not a thing to
 * sign a comment with for every visitor to read. Such an account goes by the part of the
 * address before the @, which is what its owner can change on their profile.
 *
 * An address too long to be a login is named the same way, from the address and not from the
 * login made for it ({@see AuthGateway::loginForAddress()}).
 */
final class PublicName
{
    public static function forLogin(string $login): string
    {
        if (filter_var($login, FILTER_VALIDATE_EMAIL) === false) {
            return $login;
        }

        return substr($login, 0, (int) strrpos($login, '@'));
    }
}
