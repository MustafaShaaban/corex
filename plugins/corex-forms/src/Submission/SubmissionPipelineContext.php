<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowVersion;

/**
 * Immutable state carried between typed submission stages.
 */
final readonly class SubmissionPipelineContext
{
    /**
     * This constructor is a pipeline state record; its named values are not collaborators.
     *
     * @param array<string,mixed> $values
     * @param array<string,mixed> $metadata
     */
    public function __construct(
        public Flow $flow,
        public FlowVersion $version,
        public array $values,
        public bool $isTest,
        public ?int $submissionId = null,
        public array $metadata = [],
    ) {
    }

    public function withSubmissionId(int $submissionId): self
    {
        return new self(
            $this->flow,
            $this->version,
            $this->values,
            $this->isTest,
            $submissionId,
            $this->metadata,
        );
    }

    /** @param array<string,mixed> $values */
    public function withValues(array $values): self
    {
        return new self(
            $this->flow,
            $this->version,
            $values,
            $this->isTest,
            $this->submissionId,
            $this->metadata,
        );
    }

    /** @param array<string,mixed> $metadata */
    public function withMetadata(array $metadata): self
    {
        return new self(
            $this->flow,
            $this->version,
            $this->values,
            $this->isTest,
            $this->submissionId,
            [...$this->metadata, ...$metadata],
        );
    }
}
