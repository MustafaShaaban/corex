<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Security\LoginProtection;

defined('ABSPATH') || exit;

use DateTimeImmutable;

/**
 * Pure login-protection policy helpers.
 */
final readonly class LoginProtectionPolicy
{
    public function __construct(public LoginProtectionSettings $settings)
    {
    }

    public function identityHash(LoginProtectionContext $context): string
    {
        return hash('sha256', mb_strtolower(trim($context->identity)));
    }

    public function networkHash(LoginProtectionContext $context): string
    {
        return hash('sha256', $context->clientIp);
    }

    public function retentionUntil(DateTimeImmutable $now): DateTimeImmutable
    {
        return $now->modify('+' . $this->settings->retainDays . ' days');
    }
}
