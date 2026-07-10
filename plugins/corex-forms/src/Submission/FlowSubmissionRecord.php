<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Immutable persistence input for a version-linked flow submission.
 */
final readonly class FlowSubmissionRecord
{
    /**
     * @param array<string,mixed> $values
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public string $flowSlug,
        public int $flowId,
        public int $flowVersionId,
        public string $flowLabel,
        public bool $isTest,
        public array $values,
        public array $metadata,
    ) {
    }
}
