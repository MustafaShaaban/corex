<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

use DomainException;

/**
 * Immutable environment and safety state for one delivery request.
 */
final class EmailDeliveryContext
{
    public function __construct(
        public readonly string $environment,
        public readonly bool $providerConfigured,
        public readonly bool $liveDeliveryEnabled,
        public readonly string $requestId,
    ) {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId) !== 1) {
            throw new DomainException(__('Email Studio request ID is invalid.', 'corex'));
        }
    }
}
