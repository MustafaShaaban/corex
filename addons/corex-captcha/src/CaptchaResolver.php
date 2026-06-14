<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * Selects the configured captcha driver and supplies its secret from the Config
 * engine (`captcha.driver`, `captcha.secret`). Switching provider is configuration,
 * not code. Unknown/absent driver → NullCaptcha (the honeypot still guards).
 */
final class CaptchaResolver
{
    private const ENDPOINTS = [
        'recaptcha' => 'https://www.google.com/recaptcha/api/siteverify',
        'turnstile' => 'https://challenges.cloudflare.com/turnstile/v0/siteverify',
        'hcaptcha'  => 'https://hcaptcha.com/siteverify',
    ];

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    /**
     * The provider siteverify endpoint for a key-driver, or null for none/honeypot/unknown.
     * Exposed so the "Test verification" action can probe the configured provider.
     */
    public static function endpoint(string $driver): ?string
    {
        return self::ENDPOINTS[$driver] ?? null;
    }

    public function resolve(): Captcha
    {
        $driver = (string) $this->config->get('captcha.driver', 'none');

        return match ($driver) {
            'honeypot' => new HoneypotCaptcha(),
            'recaptcha', 'turnstile', 'hcaptcha' => new RemoteCaptcha(
                self::ENDPOINTS[$driver],
                (string) $this->config->get('captcha.secret', ''),
            ),
            default => new NullCaptcha(),
        };
    }
}
