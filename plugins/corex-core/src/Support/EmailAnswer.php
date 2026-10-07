<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Support;

defined('ABSPATH') || exit;

/**
 * What a visitor typed into an email field, made safe to hand to the rules without being changed
 * into something else.
 *
 * `sanitize_email()` does not refuse an address, it removes the characters it does not accept and
 * returns what is left: `sal,ma@example.com` comes back as `salma@example.com`. Used ahead of
 * validation, that turned a mistyped address into a different person's valid one, and an answer
 * with no address in it into an empty string the `email` rule never saw.
 */
final class EmailAnswer
{
    /**
     * An address WordPress would leave alone is returned as typed. Anything else is returned as
     * plain text, so the `email` rule can refuse it by name.
     */
    public static function clean(mixed $value): string
    {
        if (! is_scalar($value)) {
            return '';
        }

        $typed = trim((string) $value);
        if (sanitize_email($typed) === $typed) {
            return $typed;
        }

        $text = sanitize_text_field($typed);

        // Stripping markup can itself leave a clean address behind. That is still not what was
        // typed, so it is dropped: nothing this returns is an address the visitor did not write.
        return sanitize_email($text) === $text ? '' : $text;
    }
}
