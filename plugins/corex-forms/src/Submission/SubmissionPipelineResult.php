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

    /**
     * Whether the submission was stored and every stage that failed is one of `$keys`: the run
     * did what the visitor asked, and what failed is recorded on the submission for the site.
     */
    public function storedFailingOnlyAt(string ...$keys): bool
    {
        $failed = array_filter($this->stages, static fn (SubmissionStageResult $stage): bool => $stage->failed());

        return $this->context->submissionId !== null
            && $failed !== []
            && array_diff(array_map(static fn (SubmissionStageResult $stage): string => $stage->key, $failed), $keys) === [];
    }

    /** The first stage that failed, or null when every stage succeeded. */
    public function failedStage(): ?SubmissionStageResult
    {
        foreach ($this->stages as $stage) {
            if ($stage->failed()) {
                return $stage;
            }
        }

        return null;
    }

    /** @param list<array{to:list<string>,subject:string,capture_id:string,path:string}> $heldEmails */
    public function withHeldEmails(array $heldEmails): self
    {
        return new self($this->context, $this->stages, $this->completed, $heldEmails);
    }
}
