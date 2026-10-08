<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\FlowProtection;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\SubmissionChallenge;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;

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
    public function __construct(private SubmissionChallenge $challenge)
    {
    }

    public function key(): string
    {
        return 'protection';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $honeypot = trim((string) ($context->values[FormSubmissionService::HONEYPOT_KEY] ?? ''));
        $token = trim((string) ($context->values['captcha_token'] ?? ''));

        $captcha = $this->challenge->verify(
            $token,
            $context->flow->slug,
            FlowProtection::normalize($context->version->configuration->protection),
        );

        $spam = [
            'honeypot' => $honeypot === '' ? 'passed' : 'failed',
            'captcha'  => $captcha->status,
            'score'    => $honeypot === '' && $captcha->passed ? 0 : 100,
        ];
        if ($captcha->detail !== null) {
            $spam['captcha_detail'] = $captcha->detail;
        }

        if ($honeypot !== '' || ! $captcha->passed) {
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
}
