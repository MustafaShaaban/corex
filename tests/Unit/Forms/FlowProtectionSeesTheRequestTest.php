<?php

/**
 * The trap field and the challenge token of a flow reach the stage that judges them.
 *
 * The validation stage runs first and hands on only the answers of the flow's fields. The
 * protection stage read the trap field and the token from what it was handed, so it never saw
 * either: a filled trap field passed, and a provider was given an empty token whatever the page
 * had sent (measured 2026-10-13, DECISIONS #358; so since the stages were written, v0.34.0).
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
use Corex\Forms\Submission\FormSubmissionPipeline;
use Corex\Forms\Submission\FormSubmissionService;
use Corex\Forms\Submission\Stages\ProtectionStage;
use Corex\Forms\Submission\Stages\ValidationStage;
use Corex\Forms\Submission\SubmissionChallenge;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Forms\Submission\SubmissionPipelineResult;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Forms\Validation\Validator;
use Corex\Security\ChallengeVerifier;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

/** A provider that answers yes to one token and no to anything else, an empty one included. */
function providerAcceptingOnly(string $accepted): ChallengeVerifier
{
    return new class($accepted) implements ChallengeVerifier {
        public function __construct(private string $accepted)
        {
        }

        public function verify(string $token): bool
        {
            return $token === $this->accepted;
        }
    };
}

/**
 * A request for a flow with one field, `email`, through the real validation and protection stages.
 *
 * @param array<string,mixed> $request
 */
function flowRequestThroughBothStages(array $request, ?ChallengeVerifier $provider = null): SubmissionPipelineResult
{
    $now = new DateTimeImmutable('2026-10-13T12:00:00+00:00');
    $flow = new Flow(7, '5e08b4c1-7c00-49fb-b854-17bf309755bb', 'contact', 'Contact', '', 'published', 1, 'none', null, 1, 1, false, 1, 1, $now, $now);
    $configuration = new FlowConfiguration(
        [['uuid' => 'email-field', 'key' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true]],
        ['email' => ['required', 'email']],
        [],
        [],
        [],
        [],
    );
    $rules = new RuleRegistry();

    return (new FormSubmissionPipeline([
        new ValidationStage(new FlowSchemaFactory(new SchemaResolver($rules)), new Validator($rules)),
        new ProtectionStage(new SubmissionChallenge($provider)),
    ]))->run(new SubmissionPipelineContext($flow, new FlowVersion(9, 7, 1, $configuration, 1, $now), $request, false));
}

it('refuses a flow submission whose trap field is filled', function () {
    $result = flowRequestThroughBothStages([
        'email' => 'visitor@example.com',
        FormSubmissionService::HONEYPOT_KEY => 'a bot wrote here',
    ]);

    expect($result->completed)->toBeFalse()
        ->and($result->context->metadata['spam']['honeypot'])->toBe('failed');
});

it('hands the provider the token the flow submission carried', function () {
    $result = flowRequestThroughBothStages(
        ['email' => 'visitor@example.com', SubmissionChallenge::TOKEN_KEY => 'the-token-the-page-was-given'],
        providerAcceptingOnly('the-token-the-page-was-given'),
    );

    expect($result->completed)->toBeTrue()
        ->and($result->context->metadata['spam']['captcha'])->toBe('passed');
});

it('keeps the request as it was received, whatever a stage hands on', function () {
    // Left out of one of the copies, the request would silently become the values of that moment.
    $request = ['email' => 'visitor@example.com', FormSubmissionService::HONEYPOT_KEY => 'a bot wrote here'];
    $context = flowRequestThroughBothStages(['email' => 'visitor@example.com'])->context;
    $started = new SubmissionPipelineContext($context->flow, $context->version, $request, false);

    $handedOn = $started->withValues(['email' => 'visitor@example.com'])->withMetadata(['spam' => []])->withSubmissionId(42);

    expect($handedOn->received)->toBe($request);
});
