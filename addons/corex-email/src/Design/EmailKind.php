<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Design;

defined('ABSPATH') || exit;

/**
 * What kind of email a message is, which decides the line its footer says about why the
 * person receives it, and whether a site's own design is given it (spec 120, story 3).
 */
final class EmailKind
{
    /** An answer from the inbox to someone who wrote. */
    public const REPLY = 'reply';

    /** A notice to the site's own people: a form was filled in, an access request came. */
    public const NOTIFICATION = 'notification';

    /** To someone who filled in a form, subscribed or applied: what they sent was received. */
    public const CONFIRMATION = 'confirmation';

    /** Newsletter mail: a new article, a welcome. */
    public const NEWSLETTER = 'newsletter';

    /** About an account on the site: a password reset, a welcome. */
    public const ACCOUNT = 'account';

    /** Anything else: a test, an email a route sends with no kind of its own. No reason line. */
    public const MESSAGE = 'message';

    private const ALL = [self::REPLY, self::NOTIFICATION, self::CONFIRMATION, self::NEWSLETTER, self::ACCOUNT, self::MESSAGE];

    /** A kind, or {@see MESSAGE} for anything that is not one. */
    public static function of(mixed $kind): string
    {
        return is_string($kind) && in_array($kind, self::ALL, true) ? $kind : self::MESSAGE;
    }

    /**
     * The footer's line saying why the person receives this email, or '' for a kind that has none.
     *
     * @param string $site The site's name, as text.
     */
    public static function reason(string $kind, string $site): string
    {
        $line = match ($kind) {
            /* translators: %s: the site's name. */
            self::REPLY        => __('You receive this email because you wrote to %s.', 'corex'),
            /* translators: %s: the site's name. */
            self::NOTIFICATION => __('You receive this email because this address receives notifications from %s.', 'corex'),
            /* translators: %s: the site's name. */
            self::CONFIRMATION => __('You receive this email because this address was entered on %s.', 'corex'),
            /* translators: %s: the site's name. */
            self::NEWSLETTER   => __('You receive this email because you subscribed to updates from %s.', 'corex'),
            /* translators: %s: the site's name. */
            self::ACCOUNT      => __('You receive this email because of an account on %s.', 'corex'),
            default            => '',
        };

        return $line === '' ? '' : sprintf($line, $site);
    }
}
