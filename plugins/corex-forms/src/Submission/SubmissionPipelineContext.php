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
 *
 * `values` is what a stage hands the next, and the validation stage reduces it to the answers of
 * the flow's fields. `received` is the request as the pipeline was handed it, and no stage
 * changes it: a stage that judges something the request carried beside its answers (the trap
 * field, the challenge token) reads it there, whichever stages ran before it.
 */
final readonly class SubmissionPipelineContext
{
    /** @var array<string,mixed> */
    public array $received;

    /**
     * This constructor is a pipeline state record; its named values are not collaborators.
     *
     * @param array<string,mixed>      $values
     * @param array<string,mixed>      $metadata
     * @param array<string,mixed>|null $received Left out where a pipeline starts: the request is its first values.
     */
    public function __construct(
        public Flow $flow,
        public FlowVersion $version,
        public array $values,
        public bool $isTest,
        public ?int $submissionId = null,
        public array $metadata = [],
        ?array $received = null,
    ) {
        $this->received = $received ?? $values;
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
            $this->received,
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
            $this->received,
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
            $this->received,
        );
    }
}
