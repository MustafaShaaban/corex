<?php

/**
 * Flow lifecycle orchestration contracts (spec 068: FR-028, FR-043, FR-044).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowConflictException;
use Corex\Forms\Flow\FlowDraftUpdate;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\FlowTransition;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Flow\FlowConfigurationValidator;
use Corex\Forms\Schema\FieldTypeRegistry;
use Corex\Forms\Success\SuccessStateRegistry;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Tests\Fixtures\Forms\InMemoryFlowStore;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function publishableFlowConfiguration(): FlowConfiguration
{
    return new FlowConfiguration(
        schema: [[
            'uuid' => 'field-1',
            'key' => 'email',
            'label' => 'Email',
            'type' => 'email',
            'required' => true,
        ]],
        validation: ['email' => ['required', 'email']],
        routing: ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
        emailRoutes: [],
        success: ['type' => 'inline', 'message' => 'Thanks'],
        placementSnapshot: ['type' => 'none'],
    );
}

/** @return array{0:FlowService,1:FlowRepository,2:Flow} */
function flowServiceFixture(FlowConfiguration $configuration): array
{
    $repository = new FlowRepository(new InMemoryFlowStore());
    $flow = $repository->save(new Flow(
        id: 0,
        uuid: '8cb9b4cb-5103-4e3d-9dde-58fac287ca26',
        slug: 'contact-sales',
        name: 'Contact sales',
        description: '',
        state: Flow::STATE_DRAFT,
        ownerId: 11,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: 0,
        testMode: false,
        createdBy: 11,
        updatedBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
    ));
    $repository->appendVersion(new FlowVersion(
        id: 0,
        flowId: $flow->id,
        versionNumber: 1,
        configuration: $configuration,
        createdBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
    ));
    $validator = new FlowConfigurationValidator(
        new FieldTypeRegistry(),
        new RuleRegistry(),
        new SuccessStateRegistry(),
    );
    $service = new FlowService($repository, $validator);

    return [$service, $repository, $flow];
}

it('publishes only a complete current draft and points to its immutable version', function () {
    [$service, $repository, $flow] = flowServiceFixture(publishableFlowConfiguration());

    $published = $service->publish(new FlowTransition(
        flowId: $flow->id,
        expectedDraftVersion: 1,
        actorId: 11,
        occurredAt: new DateTimeImmutable('2026-07-04T10:00:00+00:00'),
    ));

    expect($published->state)->toBe(Flow::STATE_PUBLISHED)
        ->and($published->publishedVersion)->toBe(1)
        ->and($published->publishedAt?->format(DATE_ATOM))->toBe('2026-07-04T10:00:00+00:00')
        ->and($repository->versions($flow->id))->toHaveCount(1);
});

it('rejects publishing an incomplete configuration without changing lifecycle state', function () {
    $invalid = new FlowConfiguration(
        schema: [],
        validation: [],
        routing: [],
        emailRoutes: [],
        success: [],
        placementSnapshot: ['type' => 'none'],
    );
    [$service, $repository, $flow] = flowServiceFixture($invalid);

    expect(fn () => $service->publish(new FlowTransition(
        $flow->id,
        1,
        11,
        new DateTimeImmutable('2026-07-04T10:00:00+00:00'),
    )))->toThrow(DomainException::class);
    expect($repository->find($flow->id)?->state)->toBe(Flow::STATE_DRAFT);
});

it('rejects unsafe schema routing email and success configurations', function (FlowConfiguration $invalid) {
    [$service, $repository, $flow] = flowServiceFixture($invalid);

    expect(fn () => $service->publish(new FlowTransition(
        $flow->id,
        1,
        11,
        new DateTimeImmutable('2026-07-04T10:00:00+00:00'),
    )))->toThrow(DomainException::class)
        ->and($repository->find($flow->id)?->state)->toBe(Flow::STATE_DRAFT);
})->with([
    'duplicate field keys' => new FlowConfiguration(
        schema: [
            ['key' => 'email', 'type' => 'email'],
            ['key' => 'email', 'type' => 'text'],
        ],
        validation: [],
        routing: ['fallback' => ['type' => 'flow_owner', 'config' => []]],
        emailRoutes: [],
        success: ['type' => 'inline', 'message' => 'Thanks'],
        placementSnapshot: [],
    ),
    'invalid routing email' => new FlowConfiguration(
        schema: [['key' => 'email', 'type' => 'email']],
        validation: [],
        routing: ['fallback' => ['type' => 'email', 'config' => ['value' => 'not-an-email']]],
        emailRoutes: [],
        success: ['type' => 'inline', 'message' => 'Thanks'],
        placementSnapshot: [],
    ),
    'incomplete email binding' => new FlowConfiguration(
        schema: [['key' => 'email', 'type' => 'email']],
        validation: [],
        routing: ['fallback' => ['type' => 'flow_owner', 'config' => []]],
        emailRoutes: [['event' => 'submitter_confirmation', 'enabled' => true, 'template_id' => 0, 'recipient' => 'email']],
        success: ['type' => 'inline', 'message' => 'Thanks'],
        placementSnapshot: [],
    ),
    'empty inline success' => new FlowConfiguration(
        schema: [['key' => 'email', 'type' => 'email']],
        validation: [],
        routing: ['fallback' => ['type' => 'flow_owner', 'config' => []]],
        emailRoutes: [],
        success: ['type' => 'inline', 'message' => ''],
        placementSnapshot: [],
    ),
]);

it('rejects stale draft writes before appending a version', function () {
    [$service, $repository, $flow] = flowServiceFixture(publishableFlowConfiguration());

    expect(fn () => $service->saveDraft(new FlowDraftUpdate(
        flowId: $flow->id,
        configuration: publishableFlowConfiguration(),
        expectedVersion: 1,
        expectedChecksum: 'stale-checksum',
        actorId: 12,
        occurredAt: new DateTimeImmutable('2026-07-04T10:00:00+00:00'),
    )))->toThrow(FlowConflictException::class);
    expect($repository->versions($flow->id))->toHaveCount(1)
        ->and($repository->find($flow->id)?->currentDraftVersion)->toBe(1);
});

it('previews the current draft and applies close expire and draft transitions', function () {
    [$service, , $flow] = flowServiceFixture(publishableFlowConfiguration());
    $published = $service->publish(new FlowTransition(
        $flow->id,
        1,
        11,
        new DateTimeImmutable('2026-07-04T10:00:00+00:00'),
    ));
    $closed = $service->close(new FlowTransition(
        $flow->id,
        1,
        11,
        new DateTimeImmutable('2026-07-04T11:00:00+00:00'),
    ));
    $reopened = $service->unpublish(new FlowTransition(
        $flow->id,
        1,
        11,
        new DateTimeImmutable('2026-07-04T12:00:00+00:00'),
    ));
    $republished = $service->publish(new FlowTransition(
        $flow->id,
        1,
        11,
        new DateTimeImmutable('2026-07-04T13:00:00+00:00'),
    ));
    $expired = $service->expire(new FlowTransition(
        $flow->id,
        1,
        11,
        new DateTimeImmutable('2026-07-04T14:00:00+00:00'),
    ));

    expect($published->state)->toBe(Flow::STATE_PUBLISHED)
        ->and($closed->state)->toBe(Flow::STATE_CLOSED)
        ->and($closed->closedAt?->format(DATE_ATOM))->toBe('2026-07-04T11:00:00+00:00')
        ->and($reopened->state)->toBe(Flow::STATE_DRAFT)
        ->and($reopened->closedAt)->toBeNull()
        ->and($republished->state)->toBe(Flow::STATE_PUBLISHED)
        ->and($expired->state)->toBe(Flow::STATE_EXPIRED)
        ->and($service->preview($flow->id)->versionNumber)->toBe(1);
});
