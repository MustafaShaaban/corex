<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

/**
 * No captcha configured: always passes. The form's honeypot + throttle still guard.
 */
final class NullCaptcha implements Captcha
{
    /**
     * @param array<string,mixed> $context
     */
    public function verify(string $token, array $context = []): bool
    {
        return true;
    }
}
