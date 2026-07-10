<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;
use Corex\Security\ChallengeVerifier;

final readonly class ProtectionStage implements SubmissionStage
{
    public function __construct(private ?ChallengeVerifier $captcha = null)
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
        $captchaPassed = $this->captcha === null || $this->captcha->verify($token);
        $metadata = ['spam' => [
            'honeypot' => $honeypot === '' ? 'passed' : 'failed',
            'captcha' => $this->captcha === null ? 'not_configured' : ($captchaPassed ? 'passed' : 'failed'),
            'score' => $honeypot === '' && $captchaPassed ? 0 : 100,
        ]];
        if ($honeypot !== '' || ! $captchaPassed) {
            return SubmissionStageResult::failure(
                $this->key(),
                $context->withMetadata($metadata),
                __('Submission protection rejected the request.', 'corex'),
            );
        }

        $values = $context->values;
        unset($values[FormSubmissionService::HONEYPOT_KEY], $values['captcha_token']);

        return SubmissionStageResult::success(
            $this->key(),
            $context->withValues($values)->withMetadata($metadata),
            __('Submission protection passed.', 'corex'),
        );
    }
}
