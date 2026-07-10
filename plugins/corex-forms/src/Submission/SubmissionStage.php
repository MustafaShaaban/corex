<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * One named, ordered stage in the visitor or marked-test submission pipeline.
 */
interface SubmissionStage
{
    public function key(): string;

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult;
}
