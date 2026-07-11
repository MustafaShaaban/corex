<?php

/**
 * @package Corex\Email
 */

declare(strict_types=1);

namespace Corex\Email\Studio;

defined('ABSPATH') || exit;

/**
 * Runtime-only checks that complement persisted template validation.
 */
final class EmailHealthContext
{
    public function __construct(
        public readonly bool $providerConfigured,
        public readonly bool $requiresSubscriptionLinks = false,
        public readonly ?string $replyTo = null,
        public readonly ?EmailLayout $layout = null,
    ) {
    }
}
