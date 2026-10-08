<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * What the challenge check concluded about one submission.
 */
final readonly class SubmissionChallengeOutcome
{
    public const PASSED         = 'passed';
    public const FAILED         = 'failed';
    public const NOT_CONFIGURED = 'not_configured';

    /**
     * @param array<string,mixed>|null $detail What a scored provider reported; null when it reported nothing.
     */
    private function __construct(public bool $passed, public string $status, public ?array $detail)
    {
    }

    /**
     * No challenge applies: no provider, or the form opted out. The submission is not refused.
     */
    public static function notConfigured(): self
    {
        return new self(true, self::NOT_CONFIGURED, null);
    }

    /**
     * @param array<string,mixed>|null $detail
     */
    public static function decided(bool $passed, ?array $detail = null): self
    {
        return new self($passed, $passed ? self::PASSED : self::FAILED, $detail);
    }
}
