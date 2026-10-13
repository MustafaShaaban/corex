<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Reset;

defined('ABSPATH') || exit;

/**
 * The addresses of a password reset on the page the account block is on.
 *
 * The names are the add-on's own. `login`, `key` and `action` are what `wp-login.php` reads,
 * and a page of the site may be read by anything.
 */
final class ResetLink
{
    public const ARG_LOGIN = 'corex_reset_login';

    public const ARG_KEY = 'corex_reset_key';

    /** On the address a visitor is left at once the key is out of it. */
    public const ARG_STEP = 'corex_reset';

    /**
     * The address written into the email.
     */
    public function emailed(int $pageId, string $login, string $key): string
    {
        // add_query_arg() writes a value as it is handed over.
        return add_query_arg(
            [
                self::ARG_LOGIN => rawurlencode($login),
                self::ARG_KEY   => rawurlencode($key),
            ],
            (string) get_permalink($pageId),
        );
    }

    /**
     * The address a visitor is sent on to once the key has been put away: the same page,
     * without the login and the key.
     */
    public function withoutTheKey(int $pageId): string
    {
        return add_query_arg(self::ARG_STEP, '1', (string) get_permalink($pageId));
    }
}
