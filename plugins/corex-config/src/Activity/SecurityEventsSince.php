<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Activity;

defined('ABSPATH') || exit;

/**
 * From when the activity log has security events (spec 114, US-SAFE-13, FR-016).
 *
 * Mode changes, refusals and sign-in lockouts reach the activity log from this release on.
 * Earlier ones stay in their own logs and are not copied, so the Security events card says from
 * when it has records instead of implying there were none before.
 */
final class SecurityEventsSince
{
    public const OPTION = 'corex_security_events_since';

    /** Stores the start, unless one is stored already: `add_option` leaves an existing value alone. */
    public function mark(int $at): void
    {
        add_option(self::OPTION, (string) $at, '', true);
    }

    /** The stored start as a Unix time, or now when nothing has been marked yet. */
    public function since(): int
    {
        return (int) get_option(self::OPTION, (string) time());
    }
}
