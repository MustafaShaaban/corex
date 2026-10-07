<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support;

defined('ABSPATH') || exit;

/**
 * What somebody typed where an email address was asked for, without it being changed into a
 * different one.
 *
 * `sanitize_email()` does not refuse an address, it removes the characters it does not accept and
 * returns what is left: `sal,ma@example.com` comes back as `salma@example.com`. Used ahead of
 * validation, that turned a mistyped address into a different person's valid one, and an answer
 * with no address in it into an empty string no rule ever saw.
 */
final class EmailAnswer
{
    /**
     * The address, when it is one WordPress would store exactly as typed; otherwise an empty
     * string. For a caller that needs an address or nothing: a subscription, an account, a reply.
     */
    public static function address(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $typed = trim((string) $value);

        return $typed !== '' && sanitize_email($typed) === $typed ? $typed : '';
    }

    /**
     * The answer for validation rules to judge. An address WordPress would leave alone is
     * returned as typed; anything else is returned as plain text, so a rule can refuse it by name.
     */
    public static function clean(mixed $value): string
    {
        $address = self::address($value);
        if ($address !== '' || ! is_scalar($value)) {
            return $address;
        }

        $text = sanitize_text_field(trim((string) $value));

        // Stripping markup can itself leave a clean address behind. That is still not what was
        // typed, so it is dropped: nothing this returns is an address the visitor did not write.
        return self::address($text) === '' ? $text : '';
    }
}
