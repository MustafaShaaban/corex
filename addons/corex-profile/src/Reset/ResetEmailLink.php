<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Reset;

defined('ABSPATH') || exit;

/**
 * Has WordPress's password-reset email link to the page the reset was asked from.
 *
 * The email stays WordPress's: `retrieve_password()` makes the key, writes the email and
 * sends it, and whatever else filters that email still does. One thing in it is changed, the
 * address that carries the key, and only while a reset asked through the account block is
 * being answered. A reset asked at `wp-login.php` is not touched (DECISIONS #341).
 */
final class ResetEmailLink
{
    /** After a plugin that writes the email its own way, so its address is the one changed. */
    private const AFTER_OTHER_FILTERS = 99;

    public function __construct(private readonly ResetLink $links)
    {
    }

    /**
     * Runs a request for a reset, with its email linking to that page.
     *
     * @template T
     *
     * @param int           $pageId  A published page with the account block, or zero for none.
     * @param callable(): T $request What asks WordPress for the email.
     *
     * @return T
     */
    public function during(int $pageId, callable $request): mixed
    {
        if ($pageId <= 0) {
            return $request();
        }

        $relink = fn (mixed $message, mixed $key, mixed $login): mixed => is_string($message)
            ? $this->toPage($pageId, $message, (string) $key, (string) $login)
            : $message;

        add_filter('retrieve_password_message', $relink, self::AFTER_OTHER_FILTERS, 3);

        try {
            return $request();
        } finally {
            remove_filter('retrieve_password_message', $relink, self::AFTER_OTHER_FILTERS);
        }
    }

    /**
     * The email's text with the address that carries this key replaced by the page's.
     *
     * The address is found by its key, not rebuilt: where login protection hides the login it
     * is not the one WordPress would write, and a plugin may have set it in markup.
     */
    public function toPage(int $pageId, string $message, string $key, string $login): string
    {
        $address = '~[^\s<>"\']*[?&]key=' . preg_quote($key, '~') . '(?=[&\s<>"\']|$)[^\s<>"\']*~';

        return preg_replace_callback(
            $address,
            fn (): string => $this->links->emailed($pageId, $login, $key),
            $message,
            1,
        ) ?? $message;
    }
}
