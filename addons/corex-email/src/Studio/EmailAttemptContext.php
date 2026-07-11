<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use Corex\Email\Message\EmailMessage;

/**
 * Shared immutable facts recorded for every recipient in one dispatch.
 */
final readonly class EmailAttemptContext
{
    public function __construct(
        public EmailMessage $message,
        public EmailDeliveryContext $delivery,
        public EmailDispatchMetadata $metadata,
        public EmailDeliveryOutcome $outcome,
    ) {
    }
}
