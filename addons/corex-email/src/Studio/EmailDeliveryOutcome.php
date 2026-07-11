<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

/**
 * Immutable outcome returned by one provider or capture execution.
 */
final readonly class EmailDeliveryOutcome
{
    public function __construct(
        public string $state,
        public string $provider,
        public string $message,
        public bool $retryable,
    ) {
    }
}
