<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

/**
 * The honeypot driver: the token is a hidden field's value — empty means a human,
 * any content means a bot.
 */
final class HoneypotCaptcha implements Captcha
{
    /**
     * @param array<string,mixed> $context
     */
    public function verify(string $token, array $context = []): bool
    {
        return trim($token) === '';
    }
}
