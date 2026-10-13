<?php

/**
 * @package Corex\Forms
 */

declare(strict_types=1);

namespace Corex\Forms\Submission\Stages;

defined('ABSPATH') || exit;

use Corex\Forms\Submission\FileFieldAnswers;
use Corex\Forms\Submission\FlowSchemaFactory;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionStage;
use Corex\Forms\Submission\SubmissionStageResult;
use Corex\Forms\Validation\Validator;

final readonly class ValidationStage implements SubmissionStage
{
    public function __construct(
        private FlowSchemaFactory $schemas,
        private Validator $validator,
    ) {
    }

    public function key(): string
    {
        return 'validation';
    }

    public function execute(SubmissionPipelineContext $context): SubmissionStageResult
    {
        $schema = $this->schemas->make($context->version->configuration);
        // A flow's route reads no file parts, so a file question has no answer here. What the
        // body says under its key is not one, and it is dropped before anything below reads the
        // answers: the rules, and what is written down beside them.
        $context          = $context->withValues(FileFieldAnswers::from($schema, $context->values));
        $boundaryMetadata = $this->boundaryMetadata($context);
        $result           = $this->validator->validate($schema, $context->values);
        if (! $result->isValid()) {
            return SubmissionStageResult::failure(
                $this->key(),
                $context->withMetadata($boundaryMetadata)->withMetadata(['validation_errors' => $result->errors]),
                __('Validation failed.', 'corex'),
            );
        }

        return SubmissionStageResult::success(
            $this->key(),
            $context->withValues($result->values)->withMetadata($boundaryMetadata),
            __('Validation passed.', 'corex'),
        );
    }

    /** @return array<string,mixed> */
    private function boundaryMetadata(SubmissionPipelineContext $context): array
    {
        $utm = [];
        foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'] as $key) {
            if (isset($context->values[$key]) && is_scalar($context->values[$key])) {
                $utm[$key] = (string) $context->values[$key];
            }
        }
        $consent = [];
        foreach ($context->version->configuration->schema as $field) {
            if (($field['personal_data_class'] ?? '') !== 'consent') {
                continue;
            }
            $key = (string) ($field['key'] ?? '');
            $consent[$key] = [
                'label' => (string) ($field['label'] ?? $key),
                'value' => $context->values[$key] ?? null,
                'statement' => (string) ($field['help_text'] ?? ''),
            ];
        }
        $hidden = [];
        foreach (['page_url', 'referrer', 'locale'] as $key) {
            if (isset($context->values[$key]) && is_scalar($context->values[$key])) {
                $hidden[$key] = (string) $context->values[$key];
            }
        }

        return ['utm' => $utm, 'consent' => $consent, 'hidden' => $hidden];
    }
}
