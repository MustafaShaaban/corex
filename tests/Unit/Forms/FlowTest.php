<?php

/**
 * Flow lifecycle and immutable version contracts (spec 068: FR-028, FR-043).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowVersion;
use Corex\Tests\Fixtures\Forms\InMemoryFlowStore;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function flowFixture(string $state = Flow::STATE_DRAFT): Flow
{
    return new Flow(
        id: 7,
        uuid: '8cb9b4cb-5103-4e3d-9dde-58fac287ca26',
        slug: 'contact-sales',
        name: 'Contact sales',
        description: 'Routes qualified enquiries.',
        state: $state,
        ownerId: 11,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 2,
        publishedVersion: $state === Flow::STATE_PUBLISHED ? 1 : 0,
        testMode: false,
        createdBy: 11,
        updatedBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-07-04T09:00:00+00:00'),
    );
}

it('declares the complete lifecycle and only permits specified transitions', function () {
    expect(Flow::states())->toBe(['draft', 'published', 'closed', 'expired'])
        ->and(flowFixture(Flow::STATE_DRAFT)->canTransitionTo(Flow::STATE_PUBLISHED))->toBeTrue()
        ->and(flowFixture(Flow::STATE_DRAFT)->canTransitionTo(Flow::STATE_CLOSED))->toBeFalse()
        ->and(flowFixture(Flow::STATE_PUBLISHED)->canTransitionTo(Flow::STATE_DRAFT))->toBeTrue()
        ->and(flowFixture(Flow::STATE_PUBLISHED)->canTransitionTo(Flow::STATE_CLOSED))->toBeTrue()
        ->and(flowFixture(Flow::STATE_PUBLISHED)->canTransitionTo(Flow::STATE_EXPIRED))->toBeTrue()
        ->and(flowFixture(Flow::STATE_CLOSED)->canTransitionTo(Flow::STATE_DRAFT))->toBeTrue()
        ->and(flowFixture(Flow::STATE_EXPIRED)->canTransitionTo(Flow::STATE_DRAFT))->toBeTrue()
        ->and(flowFixture(Flow::STATE_CLOSED)->canTransitionTo(Flow::STATE_PUBLISHED))->toBeFalse();
});

it('rejects invalid lifecycle state and version pointers', function () {
    expect(fn () => flowFixture('deleted'))->toThrow(InvalidArgumentException::class);

    expect(fn () => new Flow(
        id: 7,
        uuid: '8cb9b4cb-5103-4e3d-9dde-58fac287ca26',
        slug: 'contact-sales',
        name: 'Contact sales',
        description: '',
        state: Flow::STATE_DRAFT,
        ownerId: 11,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: 2,
        testMode: false,
        createdBy: 11,
        updatedBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-07-04T09:00:00+00:00'),
    ))->toThrow(InvalidArgumentException::class);

    expect(fn () => new Flow(
        id: -1,
        uuid: 'not-a-uuid',
        slug: 'Invalid Slug',
        name: '',
        description: '',
        state: Flow::STATE_DRAFT,
        ownerId: 0,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: 0,
        testMode: false,
        createdBy: 0,
        updatedBy: 0,
        createdAt: new DateTimeImmutable('2026-07-04T08:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-07-04T09:00:00+00:00'),
    ))->toThrow(InvalidArgumentException::class);
});

it('checksums canonical configuration snapshots independent of associative key order', function () {
    $first = new FlowVersion(
        id: 13,
        flowId: 7,
        versionNumber: 2,
        configuration: new FlowConfiguration(
            schema: [['uuid' => 'field-1', 'key' => 'email', 'type' => 'email']],
            validation: ['email' => ['required', 'email']],
            routing: ['fallback' => ['type' => 'email', 'email' => 'team@example.com']],
            emailRoutes: ['team_notification' => ['template_id' => 4, 'enabled' => true]],
            success: ['type' => 'inline', 'message' => 'Thanks'],
            placementSnapshot: ['type' => 'page', 'id' => 9],
        ),
        createdBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T09:00:00+00:00'),
    );
    $same = new FlowVersion(
        id: 14,
        flowId: 7,
        versionNumber: 2,
        configuration: new FlowConfiguration(
            schema: [['type' => 'email', 'key' => 'email', 'uuid' => 'field-1']],
            validation: ['email' => ['required', 'email']],
            routing: ['fallback' => ['email' => 'team@example.com', 'type' => 'email']],
            emailRoutes: ['team_notification' => ['enabled' => true, 'template_id' => 4]],
            success: ['message' => 'Thanks', 'type' => 'inline'],
            placementSnapshot: ['id' => 9, 'type' => 'page'],
        ),
        createdBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T09:00:00+00:00'),
    );
    $changed = new FlowVersion(
        id: 15,
        flowId: 7,
        versionNumber: 2,
        configuration: new FlowConfiguration(
            schema: [['uuid' => 'field-1', 'key' => 'email', 'type' => 'email']],
            validation: ['email' => ['required', 'email']],
            routing: ['fallback' => ['type' => 'email', 'email' => 'other@example.com']],
            emailRoutes: ['team_notification' => ['template_id' => 4, 'enabled' => true]],
            success: ['type' => 'inline', 'message' => 'Thanks'],
            placementSnapshot: ['type' => 'page', 'id' => 9],
        ),
        createdBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T09:00:00+00:00'),
    );

    expect($first->checksum)->toBe($same->checksum)
        ->and($changed->checksum)->not->toBe($first->checksum)
        ->and($first->configuration->schema[0]['key'])->toBe('email');
});

it('persists flow metadata and append-only versions through the storage seam', function () {
    $repository = new FlowRepository(new InMemoryFlowStore());
    $flow = flowFixture();
    $configuration = new FlowConfiguration(
        schema: [['uuid' => 'field-1', 'key' => 'email', 'type' => 'email']],
        validation: [],
        routing: ['fallback' => ['type' => 'flow_owner']],
        emailRoutes: [],
        success: ['type' => 'inline', 'message' => 'Thanks'],
        placementSnapshot: ['type' => 'none'],
    );

    $storedFlow = $repository->save($flow);
    $storedVersion = $repository->appendVersion(new FlowVersion(
        id: 0,
        flowId: $storedFlow->id,
        versionNumber: 1,
        configuration: $configuration,
        createdBy: 11,
        createdAt: new DateTimeImmutable('2026-07-04T09:00:00+00:00'),
    ));

    expect($repository->find($storedFlow->id)?->uuid)->toBe($flow->uuid)
        ->and($repository->findBySlug('contact-sales')?->id)->toBe($storedFlow->id)
        ->and($repository->versions($storedFlow->id))->toHaveCount(1)
        ->and($repository->findVersion($storedFlow->id, 1)?->checksum)->toBe($storedVersion->checksum);
    expect(fn () => $repository->appendVersion($storedVersion))->toThrow(DomainException::class);
});
