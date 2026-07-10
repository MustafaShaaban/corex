<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

use InvalidArgumentException;

/**
 * Executes typed submission stages in declared order with fail-fast semantics.
 */
final readonly class FormSubmissionPipeline
{
    /** @var list<SubmissionStage> */
    private array $stages;

    /** @param list<SubmissionStage> $stages */
    public function __construct(array $stages)
    {
        $keys = [];
        foreach ($stages as $stage) {
            if (! $stage instanceof SubmissionStage || isset($keys[$stage->key()])) {
                throw new InvalidArgumentException('Submission stages must be typed and uniquely keyed.');
            }
            $keys[$stage->key()] = true;
        }
        $this->stages = $stages;
    }

    public function run(SubmissionPipelineContext $context): SubmissionPipelineResult
    {
        $results = [];
        foreach ($this->stages as $stage) {
            $stageResult = $stage->execute($context);
            if ($stageResult->key !== $stage->key()) {
                throw new InvalidArgumentException('Submission stage returned a mismatched key.');
            }
            $results[] = $stageResult;
            $context = $stageResult->context;
            if ($stageResult->failed()) {
                return new SubmissionPipelineResult($context, $results, false);
            }
        }

        return new SubmissionPipelineResult($context, $results, true);
    }
}
