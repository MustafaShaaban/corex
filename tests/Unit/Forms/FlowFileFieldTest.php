<?php

/**
 * A flow's file question has no answer a request's body can give (spec 081).
 *
 * A flow built in the admin takes no file: its route reads no file parts. What the body said under
 * the question's key was validated and stored as the answer all the same, and an answer to a file
 * question is read everywhere as the number of a stored file. Reported on 2026-10-13.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Schema\SchemaResolver;
use Corex\Forms\Submission\FlowSchemaFactory;
use Corex\Forms\Submission\Stages\ValidationStage;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;

/**
 * A visitor's answers to a flow that asks for a name and a file.
 *
 * @param array<string,mixed> $values
 */
function flowAskingForAFile(array $values, bool $fileRequired): SubmissionPipelineContext
{
    $at = new DateTimeImmutable('2026-10-13T08:00:00+00:00');

    return new SubmissionPipelineContext(
        flow: new Flow(
            id: 7,
            uuid: '8cb9b4cb-5103-4e3d-9dde-58fac287ca26',
            slug: 'apply-flow',
            name: 'Apply',
            description: '',
            state: Flow::STATE_PUBLISHED,
            ownerId: 11,
            placementType: Flow::PLACEMENT_NONE,
            placementId: null,
            currentDraftVersion: 1,
            publishedVersion: 1,
            testMode: false,
            createdBy: 11,
            updatedBy: 11,
            createdAt: $at,
            updatedAt: $at,
        ),
        version: new FlowVersion(
            id: 9,
            flowId: 7,
            versionNumber: 1,
            configuration: new FlowConfiguration(
                [
                    ['key' => 'name', 'type' => 'text', 'label' => 'Name', 'required' => true],
                    ['key' => 'cv', 'type' => 'file', 'label' => 'CV', 'required' => $fileRequired],
                ],
                [],
                [],
                [],
                [],
                [],
            ),
            createdBy: 11,
            createdAt: $at,
        ),
        values: $values,
        isTest: false,
    );
}

function flowValidationStage(): ValidationStage
{
    return new ValidationStage(
        new FlowSchemaFactory(new SchemaResolver(new RuleRegistry())),
        new Validator(new RuleRegistry()),
    );
}

beforeEach(function () {
    Functions\when('__')->returnArg();
});

it('passes on no answer to a file question, whatever the body said under its key', function (mixed $typed) {
    $result = flowValidationStage()->execute(flowAskingForAFile(['name' => 'Sam', 'cv' => $typed], false));

    expect($result->failed())->toBeFalse()
        ->and($result->context->values)->toBe(['name' => 'Sam']);
})->with([
    'a number as text' => ['224'],
    'a number' => [224],
]);

it('says a required file is missing, whatever the body said under its key', function () {
    $result = flowValidationStage()->execute(flowAskingForAFile(['name' => 'Sam', 'cv' => '224'], true));

    expect($result->failed())->toBeTrue()
        ->and($result->context->metadata['validation_errors'])->toBe(['cv' => 'required'])
        // Nor is it carried on by a submission that was refused.
        ->and($result->context->values)->toBe(['name' => 'Sam']);
});

/**
 * The stage also writes down what was agreed to, from the answers as they arrived.
 */
it('keeps nothing the body said under a file question in what it records beside the answers', function () {
    $context = flowAskingForAFile(['name' => 'Sam', 'cv' => '224'], false);
    $schema  = $context->version->configuration->schema;
    $schema[1]['personal_data_class'] = 'consent';
    $asked = new SubmissionPipelineContext(
        flow: $context->flow,
        version: new FlowVersion(
            id: 9,
            flowId: 7,
            versionNumber: 1,
            configuration: new FlowConfiguration($schema, [], [], [], [], []),
            createdBy: 11,
            createdAt: $context->version->createdAt,
        ),
        values: $context->values,
        isTest: false,
    );

    $result = flowValidationStage()->execute($asked);

    expect($result->context->metadata['consent']['cv']['value'])->toBeNull();
});
