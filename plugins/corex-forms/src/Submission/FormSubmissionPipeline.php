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
 *
 * A stage named in `$continueAfterFailure` is recorded when it fails and the stages after it still
 * run: a flow email that could not be sent must not leave the submission unassigned or its actions
 * unrun (spec 122, FR-008, FR-022). The run is still not reported as completed (spec 068, FR-044).
 */
final readonly class FormSubmissionPipeline
{
    /** @var list<SubmissionStage> */
    private array $stages;

    /** @var array<string,true> */
    private array $continueAfterFailure;

    /**
     * @param list<SubmissionStage> $stages
     * @param list<string>          $continueAfterFailure the keys of stages whose failure is recorded, not fatal
     */
    public function __construct(array $stages, array $continueAfterFailure = [])
    {
        $keys = [];
        foreach ($stages as $stage) {
            if (! $stage instanceof SubmissionStage || isset($keys[$stage->key()])) {
                throw new InvalidArgumentException('Submission stages must be typed and uniquely keyed.');
            }
            $keys[$stage->key()] = true;
        }
        foreach ($continueAfterFailure as $key) {
            if (! isset($keys[$key])) {
                throw new InvalidArgumentException(sprintf('No submission stage "%s" to continue after.', $key));
            }
        }
        $this->stages = $stages;
        $this->continueAfterFailure = array_fill_keys($continueAfterFailure, true);
    }

    /**
     * @param array<string,callable(callable():SubmissionStageResult):SubmissionStageResult> $around
     *        what runs one stage, by its key: a capped flow's store runs inside its lock, and only
     *        the store (spec 122, FR-030)
     */
    public function run(SubmissionPipelineContext $context, array $around = []): SubmissionPipelineResult
    {
        $results = [];
        $completed = true;
        foreach ($this->stages as $stage) {
            $execute = static fn (): SubmissionStageResult => $stage->execute($context);
            $stageResult = isset($around[$stage->key()]) ? ($around[$stage->key()])($execute) : $execute();
            if ($stageResult->key !== $stage->key()) {
                throw new InvalidArgumentException('Submission stage returned a mismatched key.');
            }
            $results[] = $stageResult;
            $context = $stageResult->context;
            if (! $stageResult->failed()) {
                continue;
            }
            $completed = false;
            if (! isset($this->continueAfterFailure[$stage->key()])) {
                return new SubmissionPipelineResult($context, $results, false);
            }
        }

        return new SubmissionPipelineResult($context, $results, $completed);
    }
}
