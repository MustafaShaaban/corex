<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission;

defined('ABSPATH') || exit;

/**
 * Pipeline evidence plus the published visitor success definition.
 */
final readonly class FlowVisitorResult
{
    /** @param array<string,mixed> $success */
    public function __construct(
        public SubmissionPipelineResult $pipeline,
        public array $success,
    ) {
    }
}
