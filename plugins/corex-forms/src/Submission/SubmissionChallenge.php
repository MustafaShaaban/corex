<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Security\ChallengeVerifier;
use Corex\Security\VerifyingChallenge;

/**
 * Checks a submission's challenge token: the one check for a form built in the admin and a
 * form defined in code (spec 104, FR-018).
 *
 * A scored provider (reCAPTCHA v3) is judged against the server's own expectation: the action
 * derived from the form and the threshold in force. Any other provider answers yes or no.
 */
final readonly class SubmissionChallenge
{
    /** The field a form sends its token in. */
    public const TOKEN_KEY = 'captcha_token';

    public function __construct(
        private ?ChallengeVerifier $captcha = null,
        private ?FormChallengeContextFactory $contextFactory = null,
    ) {
    }

    /**
     * @param array<string,mixed> $protection The form's declaration, as `FlowProtection::normalize()` answers.
     */
    public function verify(string $token, string $slug, array $protection): SubmissionChallengeOutcome
    {
        // No provider bound (the captcha add-on is inactive, or the driver is 'none'): the
        // honeypot is the guard, and captcha is honestly "not configured" — never a silent block.
        if ($this->captcha === null) {
            return SubmissionChallengeOutcome::notConfigured();
        }

        // A form that opts out of captcha keeps the honeypot but skips the provider entirely.
        if (($protection['captcha'] ?? 'inherit') === 'off') {
            return SubmissionChallengeOutcome::notConfigured();
        }

        // Typed path: a scored provider (reCAPTCHA v3) judged against server-side expectations.
        if ($this->captcha instanceof VerifyingChallenge && $this->contextFactory !== null) {
            $verification = $this->captcha->challenge($token, $this->contextFactory->forForm($slug, $protection));

            return SubmissionChallengeOutcome::decided($verification->passed(), $verification->toArray());
        }

        // A provider that answers yes or no (Turnstile, hCaptcha, a site's own). No token is a no.
        return SubmissionChallengeOutcome::decided($this->captcha->verify($token));
    }
}
