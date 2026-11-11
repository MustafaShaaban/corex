<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Settings;

defined('ABSPATH') || exit;

use Corex\Support\Config\ConfigInterface;

/**
 * What each provider still needs, in words, read from the value in force (spec 119, US-SET-03,
 * FR-012 to FR-014).
 *
 * Asked of the config engine, so a key set in the site's `.env` counts as set. Settings and the
 * captcha card on the Add-ons screen ask the same questions here, so the two never disagree.
 * Each sentence says what the missing key stops, as the code does it: with no secret the captcha
 * resolver falls back to the honeypot; with no site key the provider's widget never loads, so a
 * protected form sends no token and is refused; without a PageSpeed key the check runs on Google's
 * shared keyless quota.
 */
final class ProviderSetup
{
    private const KEYED_CAPTCHAS = ['recaptcha', 'turnstile', 'hcaptcha'];

    public function __construct(private readonly ConfigInterface $config)
    {
    }

    /** A key-based captcha provider is chosen and a key it needs is missing. */
    public function captchaNeedsSetup(): bool
    {
        return $this->captchaNeed() !== '';
    }

    /** What the captcha provider needs and what that means for forms; empty when nothing. */
    public function captchaNeed(): string
    {
        if (! in_array($this->text('captcha.driver'), self::KEYED_CAPTCHAS, true)) {
            return '';
        }

        $siteKey = $this->text('captcha.site_key') !== '';
        $secret  = $this->text('captcha.secret') !== '';

        return match (true) {
            ! $siteKey && ! $secret => __('The site key and the secret key are missing, so forms are protected by the honeypot only.', 'corex'),
            ! $secret               => __('The secret key is missing, so forms are protected by the honeypot only.', 'corex'),
            ! $siteKey              => __('The site key is missing, so the provider\'s check cannot load on your forms, and protected forms refuse what visitors send.', 'corex'),
            default                 => '',
        };
    }

    /** Whether the captcha provider has what a test needs. */
    public function captchaTestable(): bool
    {
        return in_array($this->text('captcha.driver'), self::KEYED_CAPTCHAS, true) && ! $this->captchaNeedsSetup();
    }

    /** What the PageSpeed key's absence means; empty with a key. */
    public function pageSpeedNeed(): string
    {
        return $this->text('insights.psi.key') === ''
            ? __('No PageSpeed key is saved, so performance checks share Google\'s keyless quota and are refused when it runs out.', 'corex')
            : '';
    }

    /** The half of the Cloudflare pair that is missing, when only one half is set. */
    public function cloudflareNeed(): string
    {
        $token   = $this->text('insights.cloudflare.token') !== '';
        $account = $this->text('insights.cloudflare.account_id') !== '';

        return match (true) {
            $token && ! $account => __('The Cloudflare account ID is missing, so the security scan cannot run. Add it to use the token.', 'corex'),
            ! $token && $account => __('The Cloudflare API token is missing, so the security scan cannot run. Add it to use the account ID.', 'corex'),
            default              => '',
        };
    }

    /** Whether a key of the provider is set, so there is something to test. */
    public function hasKeyFor(string $provider): bool
    {
        return match ($provider) {
            ProviderTestLog::PAGESPEED  => $this->text('insights.psi.key') !== '',
            ProviderTestLog::CLOUDFLARE => $this->text('insights.cloudflare.token') !== '',
            ProviderTestLog::CAPTCHA    => $this->captchaTestable(),
            default                     => false,
        };
    }

    private function text(string $key): string
    {
        $value = $this->config->get($key, '');

        return is_scalar($value) ? trim((string) $value) : '';
    }
}
