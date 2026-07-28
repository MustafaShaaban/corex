<?php

/**
 * @package Corex\Guides
 */

declare(strict_types=1);

namespace Corex\Guides\Support;

defined('ABSPATH') || exit;

/**
 * What happened to a support message (spec 087, FR-007).
 *
 * A separate type rather than a bool because "not sent" and "we never tried" are different things
 * to the person who typed the message, and because the reason has to be safe to show: a transport
 * failure string can carry a host name, a user name, or a credential, so it never reaches the
 * screen. `reason` is one of this class's own constants; the transport's own words go to the log.
 */
final readonly class SupportDelivery
{
    public const REASON_NO_RECIPIENT = 'no_recipient';
    public const REASON_TRANSPORT    = 'transport';

    private function __construct(
        public bool $sent,
        public string $reason,
    ) {
    }

    public static function sent(): self
    {
        return new self(true, '');
    }

    public static function failed(string $reason): self
    {
        return new self(false, $reason);
    }
}
