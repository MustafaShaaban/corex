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
 * when it has records instead of implying there were none before. Written once: `add_option`
 * leaves an existing value alone.
 */
final class SecurityEventsSince
{
    public const OPTION = 'corex_security_events_since';

    /** Stamps now as the start, unless a start is already stored, and answers the start. */
    public function mark(?int $now = null): int
    {
        add_option(self::OPTION, (string) ($now ?? time()), '', true);

        return (int) get_option(self::OPTION, (string) ($now ?? time()));
    }
}
