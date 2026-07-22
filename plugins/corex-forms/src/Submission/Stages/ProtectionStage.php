<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowProtection;
use Corex\Forms\Submission\FormChallengeContextFactory;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;
use Corex\Security\ChallengeVerifier;
use Corex\Security\VerifyingChallenge;

/**
 * Honeypot + captcha protection.
 *
 * The honeypot and throttle checks are unchanged. What changed is captcha: where this stage used
 * to call a boolean `verify()` on an always-empty token — so configuring reCAPTCHA rejected every
 * real submission — it now runs the scored, typed verdict when the provider supports it, judging
 * the token against the *server's* expected action and threshold. The typed outcome, including the
 * effective threshold that judged the request, is persisted for the administrator to inspect.
 */
final readonly class ProtectionStage implements SubmissionStage
{
    public function __construct(
        private ?ChallengeVerifier $captcha = null,
        private ?FormChallengeContextFactory $contextFactory = null,
    ) {
    }

    public function key(): string
    {
        return 'protection';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $honeypot = trim((string) ($context->values[FormSubmissionService::HONEYPOT_KEY] ?? ''));
        $token = trim((string) ($context->values['captcha_token'] ?? ''));

        [$captchaPassed, $captchaMeta] = $this->verifyCaptcha($token, $context);

        $spam = [
            'honeypot' => $honeypot === '' ? 'passed' : 'failed',
            'captcha'  => $captchaMeta['status'],
            'score'    => $honeypot === '' && $captchaPassed ? 0 : 100,
        ];
        if (isset($captchaMeta['detail'])) {
            $spam['captcha_detail'] = $captchaMeta['detail'];
        }

        if ($honeypot !== '' || ! $captchaPassed) {
            return SubmissionStageResult::failure(
                $this->key(),
                $context->withMetadata(['spam' => $spam]),
                __('Submission protection rejected the request.', 'corex'),
            );
        }

        $values = $context->values;
        unset($values[FormSubmissionService::HONEYPOT_KEY], $values['captcha_token']);

        return SubmissionStageResult::success(
            $this->key(),
            $context->withValues($values)->withMetadata(['spam' => $spam]),
            __('Submission protection passed.', 'corex'),
        );
    }

    /**
     * @return array{0:bool,1:array<string,mixed>} [passed, metadata]
     */
    private function verifyCaptcha(string $token, SubmissionPipelineContext $context): array
    {
        // No provider bound (the captcha add-on is inactive, or the driver is 'none'): the
        // honeypot is the guard, and captcha is honestly "not configured" — never a silent block.
        if ($this->captcha === null) {
            return [true, ['status' => 'not_configured']];
        }

        // A form that opts out of captcha keeps the honeypot but skips the provider entirely.
        $protection = FlowProtection::normalize($context->version->configuration->protection);
        if (($protection['captcha'] ?? 'inherit') === 'off') {
            return [true, ['status' => 'not_configured']];
        }

        // Typed path: a scored provider (reCAPTCHA v3) judged against server-side expectations.
        if ($this->captcha instanceof VerifyingChallenge && $this->contextFactory !== null) {
            $verification = $this->captcha->challenge($token, $this->contextFactory->forContext($context));

            return [
                $verification->passed(),
                [
                    'status' => $verification->passed() ? 'passed' : 'failed',
                    'detail' => $verification->toArray(),
                ],
            ];
        }

        // Legacy boolean path (turnstile/hcaptcha or a custom driver).
        $passed = $this->captcha->verify($token);

        return [$passed, ['status' => $passed ? 'passed' : 'failed']];
    }
}
