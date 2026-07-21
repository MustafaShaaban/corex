<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Events\Event;

/**
 * Dispatched when an Email Studio delivery attempt fails, so the Notification Center can alert the
 * email managers without reaching into the attempts store. Immutable: carries the attempt, provider,
 * a secret-free reason (from the mail result's contract), the dispatch source (test/route/resend),
 * and whether it is retryable. Notification policy (e.g. ignoring test sends) lives in the consumer.
 */
final class EmailStudioDeliveryFailedEvent implements Event
{
    public function __construct(
        public readonly string $attemptId,
        public readonly string $provider,
        public readonly string $safeReason,
        public readonly string $source,
        public readonly bool $retryable,
    ) {
    }
}
