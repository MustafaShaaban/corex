<?php

/**
 * @package Corex\Core
 */

declare(strict_types=1);

namespace Corex\Mail;

defined('ABSPATH') || exit;

/**
 * The id an email is sent under, which is what a later email names to say it answers this one
 * (spec 106, FR-037).
 *
 * CoreX chooses it. Left to WordPress, its mailer makes one at the moment of sending and there
 * is no reading it back, so nothing could say which email a reply continues. Made from an id
 * the email already has, it is known before the email leaves: the attempt's own, for an email
 * Email Studio sends; the request's, for one a site sends through {@see Mailer}.
 *
 * Part of that id and not all of it. WordPress's mailer rewrites a header it finds too long
 * as an encoded word, which no mail program reads as an id; through PHP's own `mail()` that
 * is anything past 47 characters, and a whole id with a host of nine letters is 48. So an id
 * is twenty characters of it where the host leaves room, and as few as ten where it does not:
 * with a host of up to 34 characters the id fits. Ten is forty random bits and twenty is
 * seventy-four, the rest being the same in every such id: one email in more than a site sends.
 * On a longer host an id is over the line, and a reply sent through `mail()` leaves without
 * naming the email it answers.
 */
final class MessageId
{
    /** How much of an id an email's id is made of, where its host leaves room. */
    private const LONGEST = 20;

    /** And the least: fewer would be two emails under one id within a site's lifetime. */
    private const SHORTEST = 10;

    /** What a header's value may be before the mailer rewrites it, through `mail()`. */
    public const FITS = 47;

    /**
     * `<something@host>`: no space, no bracket inside, one `@`, no dot at an end or beside
     * another. What a header may carry, and no wider than what WordPress's mailer takes for an
     * id: given what it does not take, it sends under one of its own and says nothing.
     */
    private const SHAPE = '/^<[A-Za-z0-9_+-]+(?:\.[A-Za-z0-9_+-]+)*@[A-Za-z0-9-]+(?:\.[A-Za-z0-9-]+)*>\z/';

    /**
     * @param string $attemptId The attempt's id, or the mail request's.
     * @param string $host      The site's host, as {@see hostOf()} answers it.
     *
     * @return string The id, in its angle brackets, or '' where there is no host to make one of.
     */
    public static function of(string $attemptId, string $host): string
    {
        // The brackets and the `@` are three of the characters there is room for.
        $length = max(self::SHORTEST, min(self::LONGEST, self::FITS - 3 - strlen($host)));
        $id = sprintf('<%s@%s>', substr(str_replace('-', '', strtolower($attemptId)), 0, $length), $host);

        return $host !== '' && self::isOne($id) ? $id : '';
    }

    public static function isOne(mixed $value): bool
    {
        return is_string($value) && strlen($value) <= 250 && preg_match(self::SHAPE, $value) === 1;
    }

    /**
     * The ids in a string of them, in order: what a `References` header holds.
     *
     * @return list<string>
     */
    public static function allIn(string $ids): array
    {
        return array_values(array_filter((array) preg_split('/\s+/', trim($ids)), [self::class, 'isOne']));
    }

    /**
     * The host an id is made of, from the site's address: its name, lower case, without a port.
     * '' for an address that has none a mail server would take for a name.
     */
    public static function hostOf(string $siteAddress): string
    {
        $host = strtolower((string) parse_url($siteAddress, PHP_URL_HOST));

        return preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?\z/', $host) === 1 ? $host : '';
    }
}
