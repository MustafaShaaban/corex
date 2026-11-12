<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * The site-wide captcha settings a token is judged against, read from stored configuration and never
 * from the request: the provider in use, the score a reCAPTCHA v3 token must reach, and the hostnames a
 * token may come from (by default this site's own). Shared by every CoreX form that checks a token, so a
 * form drawn by the forms plugin and one drawn by an add-on are judged alike (spec 071, spec 115).
 */
final readonly class ChallengeSettings
{
    public const DEFAULT_THRESHOLD = 0.3;

    /** The providers CoreX places a challenge for. */
    public const PLACED_DRIVERS = ['recaptcha', 'turnstile', 'hcaptcha'];

    /** Those of them whose challenge is a widget the visitor sees. reCAPTCHA v3 shows none. */
    public const WIDGET_DRIVERS = ['turnstile', 'hcaptcha'];

    public function __construct(private ConfigInterface $config)
    {
    }

    /** A provider is chosen and its secret entered: a form needs the provider's check. */
    public function providerConfigured(): bool
    {
        $driver = (string) $this->config->get('captcha.driver', 'none');
        $secret = (string) $this->config->get('captcha.secret', '');

        return in_array($driver, self::PLACED_DRIVERS, true) && $secret !== '';
    }

    /** The provider whose widget a protected form shows, or '' when the provider shows none. */
    public function widgetProvider(): string
    {
        $driver = (string) $this->config->get('captcha.driver', 'none');

        return in_array($driver, self::WIDGET_DRIVERS, true) ? $driver : '';
    }

    /** The key the provider's widget is drawn with. It is public by design; the secret is not. */
    public function siteKey(): string
    {
        return (string) $this->config->get('captcha.site_key', '');
    }

    public function threshold(): float
    {
        $raw = $this->config->get('captcha.score_threshold', self::DEFAULT_THRESHOLD);

        return is_numeric($raw) ? self::clamp((float) $raw) : self::DEFAULT_THRESHOLD;
    }

    /** Constrain a score threshold to the provider-supported range. */
    public static function clamp(float $threshold): float
    {
        return max(0.0, min(1.0, $threshold));
    }

    /**
     * @return list<string>
     */
    public function allowedHostnames(): array
    {
        $configured = $this->config->get('captcha.allowed_hostnames', '');
        $hosts = is_array($configured)
            ? $configured
            : array_map('trim', explode(',', (string) $configured));

        $hosts = array_values(array_filter(array_map('strval', $hosts), static fn (string $h): bool => $h !== ''));

        if ($hosts === []) {
            $siteHost = (string) wp_parse_url((string) home_url(), PHP_URL_HOST);
            if ($siteHost !== '') {
                $hosts = [$siteHost];
            }
        }

        return $hosts;
    }
}
