<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Queue;

defined('ABSPATH') || exit;

use Corex\Support\Config\FeatureFlags;

/**
 * The pure decision to queue a mail instead of sending it immediately: queue only when
 * a queue backend is available AND the `mail_queue` feature flag is on. Keeping it in
 * one testable method makes the "never a hard dependency on Action Scheduler" guarantee
 * explicit — availability is passed in, not detected here.
 */
final class MailQueueGate
{
    public function __construct(private readonly FeatureFlags $flags)
    {
    }

    public function shouldQueue(bool $backendAvailable): bool
    {
        return $backendAvailable && $this->flags->enabled('mail_queue');
    }
}
