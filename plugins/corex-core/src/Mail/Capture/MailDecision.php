<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

/**
 * What happens to one email (spec 113, research R3).
 */
enum MailDecision: string
{
    /** Sent as it is today. Production, Coming soon and Maintenance always answer this. */
    case Deliver = 'deliver';

    /** Kept in Email Studio instead of sent: a development or staging site. */
    case Capture = 'capture';

    /** Kept in Email Studio as held for a test: a flow's marked test, on any site. */
    case Hold = 'hold';

    /** Neither sent nor kept, because there is nowhere to keep it: recorded in the activity log. */
    case Refuse = 'refuse';

    public function keeps(): bool
    {
        return $this === self::Capture || $this === self::Hold;
    }
}
