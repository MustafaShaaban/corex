<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Final context and ordered evidence from a pipeline run.
 */
final readonly class SubmissionPipelineResult
{
    /**
     * @param list<SubmissionStageResult>                                                     $stages
     * @param list<array{to:list<string>,subject:string,capture_id:string,path:string}> $heldEmails
     *        the emails a marked test held instead of sending (spec 113, US3)
     */
    public function __construct(
        public SubmissionPipelineContext $context,
        public array $stages,
        public bool $completed,
        public array $heldEmails = [],
    ) {
    }

    /** @param list<array{to:list<string>,subject:string,capture_id:string,path:string}> $heldEmails */
    public function withHeldEmails(array $heldEmails): self
    {
        return new self($this->context, $this->stages, $this->completed, $heldEmails);
    }
}
