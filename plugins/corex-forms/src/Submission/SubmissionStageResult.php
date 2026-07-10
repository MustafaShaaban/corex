<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Typed, traceable outcome from one pipeline stage.
 */
final readonly class SubmissionStageResult
{
    public const STATE_SUCCESS = 'success';
    public const STATE_FAILURE = 'failure';

    private function __construct(
        public string $key,
        public string $state,
        public string $message,
        public SubmissionPipelineContext $context,
        public bool $retryable,
    ) {
    }

    public static function success(
        string $key,
        SubmissionPipelineContext $context,
        string $message,
    ): self {
        return new self($key, self::STATE_SUCCESS, $message, $context, false);
    }

    public static function failure(
        string $key,
        SubmissionPipelineContext $context,
        string $message,
        bool $retryable = false,
    ): self {
        return new self($key, self::STATE_FAILURE, $message, $context, $retryable);
    }

    public function failed(): bool
    {
        return $this->state === self::STATE_FAILURE;
    }
}
