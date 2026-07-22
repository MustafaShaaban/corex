<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowProtection;
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

    public function __construct(private ConfigInterface $config)
    {
    }

    public function forContext(SubmissionPipelineContext $pipeline): ChallengeContext
    {
        $protection = FlowProtection::normalize($pipeline->version->configuration->protection);

        $action = CaptchaAction::forFlow(
            $pipeline->flow->slug,
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

    public function providerConfigured(): bool
    {
        $driver = (string) $this->config->get('captcha.driver', 'none');
        $secret = (string) $this->config->get('captcha.secret', '');

        return $driver === 'recaptcha' && $secret !== '';
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
        $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash((string) $_SERVER['REMOTE_ADDR'])) : '';

        return $ip !== '' ? $ip : null;
    }
}
