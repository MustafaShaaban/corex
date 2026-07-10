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
    /** @param list<SubmissionStageResult> $stages */
    public function __construct(
        public SubmissionPipelineContext $context,
        public array $stages,
        public bool $completed,
    ) {
    }
}
