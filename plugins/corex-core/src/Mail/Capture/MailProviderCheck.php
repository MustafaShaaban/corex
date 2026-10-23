<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Mail\Capture;

defined('ABSPATH') || exit;

/**
 * Whether the configured mail provider is one CoreX Mail sends through. Staging sends for real
 * only with such a provider and live delivery on, for Email Studio's sends and every other path
 * alike (spec 113, FR-011), so both ask this one question. CoreX Mail binds it.
 */
interface MailProviderCheck
{
    public function providerConfigured(): bool;
}
