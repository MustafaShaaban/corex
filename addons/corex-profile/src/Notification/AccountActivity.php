<?php

/**
 * @package Corex\Profile
 */

declare(strict_types=1);

namespace Corex\Profile\Notification;

defined('ABSPATH') || exit;

/**
 * The activity the account add-on records, and what it says of each to the person whose
 * account it is.
 *
 * A kind is recorded by one of these constants and listed in the account block by its
 * sentence, so nothing the add-on records is without one. The activity stream holds many
 * other kinds (an export, an import, a release); the add-on has no sentence for those, and
 * the block does not list them (spec 068, FR-161g).
 *
 * A sentence says what happened to the account and not who did it: the list is also where
 * a person notices something they did not do.
 */
final class AccountActivity
{
    public const PROFILE_UPDATED           = 'profile.updated';
    public const OTHER_SESSIONS_SIGNED_OUT = 'sessions.revoked_others';

    /**
     * @return list<string>
     */
    public static function kinds(): array
    {
        return [self::PROFILE_UPDATED, self::OTHER_SESSIONS_SIGNED_OUT];
    }

    /**
     * What a row of the block's "Recent activity" says, or nothing for a kind that is not
     * the add-on's.
     */
    public static function sentence(string $kind): string
    {
        return match ($kind) {
            self::PROFILE_UPDATED           => __('Your profile was updated.', 'corex'),
            self::OTHER_SESSIONS_SIGNED_OUT => __('Your other sessions were signed out.', 'corex'),
            default                         => '',
        };
    }
}
