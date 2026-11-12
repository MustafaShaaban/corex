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
use Corex\Security\ChallengeSettings;

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
    /**
     * @param ChallengeSettings $settings The site-wide settings, shared with every other CoreX form that checks
     *                                    a token (spec 115).
     */
    public function __construct(private ChallengeSettings $settings, private ClientAddress $client)
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
            : $this->settings->threshold();

        return new ChallengeContext(
            expectedAction: $action,
            threshold: $threshold,
            allowedHostnames: $this->settings->allowedHostnames(),
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
        return $this->settings->widgetProvider();
    }

    /**
     * The key the provider's widget is rendered with. It is public by design; the secret is not.
     */
    public function siteKey(): string
    {
        return $this->settings->siteKey();
    }

    public function providerConfigured(): bool
    {
        return $this->settings->providerConfigured();
    }

    private function remoteIp(): ?string
    {
        $ip = $this->client->current();

        return $ip !== '' ? $ip : null;
    }
}
