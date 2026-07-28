<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Admin\Errors;

defined('ABSPATH') || exit;

/**
 * What kind of refusal a person has met (spec 083, FR-003).
 *
 * Deliberately free of copy and of WordPress: the presenter owns the words, so this can be reasoned
 * about and tested without booting anything, and a translation can never change a classification.
 */
enum AdminErrorKind: string
{
    /** The viewer is signed in and lacks the capability. */
    case Denied = 'denied';

    /** The address matches no screen. Nothing was refused. */
    case NotFound = 'not-found';

    /** A link or form carried a nonce that is no longer valid. */
    case Expired = 'expired';

    /** The session is gone; signing in again is the way forward. */
    case Session = 'session';

    /** Too many attempts, too quickly. */
    case RateLimited = 'rate-limited';

    /** The site is deliberately unavailable — maintenance, or a dependency that is down. */
    case Unavailable = 'unavailable';

    /** Something went wrong that none of the above describes. */
    case Failed = 'failed';

    /**
     * The HTTP status this kind implies when the caller supplied none.
     *
     * A caller's own status always wins: `wp_die()` is given one by whoever called it, and that
     * caller knows something this enum does not.
     */
    public function status(): int
    {
        return match ($this) {
            self::Denied => 403,
            self::NotFound => 404,
            self::Expired => 403,
            self::Session => 401,
            self::RateLimited => 429,
            self::Unavailable => 503,
            self::Failed => 500,
        };
    }

    /**
     * The CSS modifier appended to the standalone document's body class.
     *
     * Matches {@see \Corex\Admin\StandalonePage::document()}'s `$variant`, which strips anything
     * outside `[a-z0-9-]` — so every case here must already be in that alphabet.
     */
    public function variant(): string
    {
        return $this->value;
    }
}
