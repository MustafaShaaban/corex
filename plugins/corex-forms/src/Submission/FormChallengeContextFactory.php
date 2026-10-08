<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowProtection;
use Corex\Http\ClientAddress;
use Corex\Security\ChallengeContext;
use Corex\Support\Config\ConfigInterface;

/**
 * Builds the server-side {@see ChallengeContext} a submission is judged against.
 *
 * Every value here is resolved from stored configuration — the form's own protection block and
 * the global captcha settings — never from the request. The effective threshold is
 * form-override → global → conservative default; the action is derived from the flow slug (or a
 * form override); the allowed hostnames come from an explicit allowlist that defaults to this
 * site's own host. That is what lets the verifier trust the expectation rather than the token.
 */
final readonly class FormChallengeContextFactory
{
    private const DEFAULT_THRESHOLD = 0.3;

    /** The providers CoreX places a challenge for. A token is checked for any driver that is bound. */
    private const PLACED_DRIVERS = ['recaptcha', 'turnstile', 'hcaptcha'];

    /** Those of them whose challenge is a widget the visitor sees. reCAPTCHA v3 shows none. */
    private const WIDGET_DRIVERS = ['turnstile', 'hcaptcha'];

    public function __construct(private ConfigInterface $config, private ClientAddress $client)
    {
    }

    /**
     * @param array<string,mixed> $protection The form's declaration, as `FlowProtection::normalize()` answers.
     */
    public function forForm(string $slug, array $protection): ChallengeContext
    {
        $action = CaptchaAction::forFlow(
            $slug,
            isset($protection['action']) ? (string) $protection['action'] : null,
        );

        $threshold = isset($protection['threshold'])
            ? FlowProtection::clampThreshold((float) $protection['threshold'])
            : $this->globalThreshold();

        return new ChallengeContext(
            expectedAction: $action,
            threshold: $threshold,
            allowedHostnames: $this->allowedHostnames(),
            remoteIp: $this->remoteIp(),
        );
    }

    /**
     * Whether this form should be captcha-protected: its own `off` opts out entirely; otherwise
     * it follows whether the site has a real provider configured. Consumed by the renderer to
     * decide whether to enqueue the provider script and stamp the token field.
     */
    public function isProtected(array $protection): bool
    {
        $mode = is_string($protection['captcha'] ?? null) ? $protection['captcha'] : 'inherit';
        if ($mode === 'off') {
            return false;
        }

        return $this->providerConfigured();
    }

    /**
     * The provider whose widget a protected form shows, or '' when the provider shows none.
     */
    public function widgetProvider(): string
    {
        $driver = (string) $this->config->get('captcha.driver', 'none');

        return in_array($driver, self::WIDGET_DRIVERS, true) ? $driver : '';
    }

    /**
     * The key the provider's widget is rendered with. It is public by design; the secret is not.
     */
    public function siteKey(): string
    {
        return (string) $this->config->get('captcha.site_key', '');
    }

    public function providerConfigured(): bool
    {
        $driver = (string) $this->config->get('captcha.driver', 'none');
        $secret = (string) $this->config->get('captcha.secret', '');

        return in_array($driver, self::PLACED_DRIVERS, true) && $secret !== '';
    }

    private function globalThreshold(): float
    {
        $raw = $this->config->get('captcha.score_threshold', self::DEFAULT_THRESHOLD);

        return is_numeric($raw) ? FlowProtection::clampThreshold((float) $raw) : self::DEFAULT_THRESHOLD;
    }

    /** @return list<string> */
    private function allowedHostnames(): array
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

    private function remoteIp(): ?string
    {
        $ip = $this->client->current();

        return $ip !== '' ? $ip : null;
    }
}
