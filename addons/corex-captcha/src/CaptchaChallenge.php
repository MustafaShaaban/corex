<?php

/**
 * @package Corex\Captcha
 */

declare(strict_types=1);

namespace Corex\Captcha;

defined('ABSPATH') || exit;

use Corex\Http\ClientAddress;
use Corex\Security\ChallengeContext;
use Corex\Security\ChallengeFields;
use Corex\Security\ChallengeSettings;
use Corex\Security\FormChallenge;
use Corex\Security\VerifyingChallenge;

/**
 * The provider's check for a CoreX form that the forms plugin does not draw: account registration, the
 * newsletter signup, and a site's own call request or job application form (spec 115, US-AUT-02 and
 * US-VIS-11). One way to print the check and one way to judge it, so these forms behave like every other
 * protected CoreX form.
 *
 * Printing it ({@see fields()}) also declares that the page needs the provider's script, which
 * {@see CaptchaAssetController} loads at the end of the page. A page that prints no check loads nothing.
 * Judging it ({@see refusal()}) uses the same site-wide settings as the forms plugin: a reCAPTCHA v3 token
 * must come from this site, for the form's action, with the configured score.
 *
 * Request-scoped: bound as a container singleton, it lives for one request.
 */
final class CaptchaChallenge implements FormChallenge
{
    /** The attribute a protected form carries, which the provider's scripts look for. */
    public const FORM_ATTRIBUTE = 'data-corex-captcha';

    private bool $declared = false;

    public function __construct(
        private readonly Captcha $captcha,
        private readonly ChallengeSettings $settings,
        private readonly ClientAddress $client,
    ) {
    }

    /** Whether forms need the check: a provider is chosen and its keys entered. */
    public function isRequired(): bool
    {
        return $this->settings->providerConfigured();
    }

    /**
     * The token field and, for a provider that shows one, the place for its widget; '' when no check is
     * needed. Printing it declares that this page needs the provider's script.
     */
    public function fields(string $action): string
    {
        if (! $this->isRequired()) {
            return '';
        }

        $this->declared = true;

        return ChallengeFields::token($action)
            . ChallengeFields::widgetPlace($this->settings->widgetProvider(), $this->settings->siteKey());
    }

    /** The attribute for the form element: it tells the provider's scripts this form is theirs. */
    public function formAttribute(): string
    {
        return $this->isRequired() ? self::FORM_ATTRIBUTE : '';
    }

    /** Whether a form on this page printed the check. */
    public function declared(): bool
    {
        return $this->declared;
    }

    /**
     * Null when the token passes; otherwise what to tell the visitor. With no provider, or the honeypot,
     * every token passes, as before.
     */
    public function refusal(string $token, string $action): ?string
    {
        $token = trim($token);

        if ($this->passes($token, $action)) {
            return null;
        }

        return $token === ''
            ? __('Please complete the check before sending.', 'corex')
            : __('The check failed. Please try again.', 'corex');
    }

    private function passes(string $token, string $action): bool
    {
        if (! $this->captcha instanceof VerifyingChallenge) {
            return $this->captcha->verify($token);
        }

        $ip = $this->client->current();

        return $this->captcha->challenge($token, new ChallengeContext(
            expectedAction: $action,
            threshold: $this->settings->threshold(),
            allowedHostnames: $this->settings->allowedHostnames(),
            remoteIp: $ip !== '' ? $ip : null,
        ))->passed();
    }
}
