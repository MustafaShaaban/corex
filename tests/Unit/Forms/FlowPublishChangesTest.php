<?php

/**
 * Publishing changes to a live flow, copying a version into the draft, and the version a visitor's
 * submission is judged by (spec 122, US-FLW-01; DECISIONS #470).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowConfigurationValidator;
use Corex\Forms\Flow\FlowConflictException;
use Corex\Forms\Flow\FlowDraftUpdate;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\FlowTransition;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Schema\FieldTypeRegistry;
use Corex\Forms\Success\SuccessStateRegistry;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Support\BootLogger;
use Corex\Tests\Fixtures\Forms\InMemoryFlowStore;

beforeEach(function () {
    Functions\when('__')->returnArg();
});

function liveFlowConfiguration(string $label, array $protection = []): FlowConfiguration
{
    return new FlowConfiguration(
        schema: [['uuid' => 'f-1', 'key' => 'email', 'label' => $label, 'type' => 'email', 'required' => true]],
        validation: ['email' => ['required', 'email']],
        routing: ['rules' => [], 'fallback' => ['type' => 'flow_owner', 'config' => []]],
        emailRoutes: [],
        success: ['type' => 'inline', 'message' => 'Thanks'],
        placementSnapshot: ['type' => 'none'],
        protection: $protection,
    );
}

/** @return array{0:FlowService,1:FlowRepository,2:int} A service over a flow published at version 1 by user 11. */
function liveFlowFixture(): array
{
    $repository = new FlowRepository(new InMemoryFlowStore(), new BootLogger(debug: false));
    $at = new DateTimeImmutable('2026-10-20T08:00:00+00:00');
    $flow = $repository->save(new Flow(
        id: 0,
        uuid: '1d5d3c2b-8a8f-4b4e-9c4f-0e2a7b6c5d4e',
        slug: 'apply',
        name: 'Apply',
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
        createdAt: $at,
        updatedAt: $at,
    ));
    $repository->appendVersion(new FlowVersion(0, $flow->id, 1, liveFlowConfiguration('Email'), 11, $at));
    $service = new FlowService($repository, new FlowConfigurationValidator(new FieldTypeRegistry(), new RuleRegistry(), new SuccessStateRegistry()));
    $service->publish(new FlowTransition($flow->id, 1, 11, new DateTimeImmutable('2026-10-20T09:00:00+00:00')));

    return [$service, $repository, $flow->id];
}

function saveLiveDraft(FlowService $service, int $flowId, FlowConfiguration $configuration, int $actor, string $at): FlowVersion
{
    $current = $service->currentVersion($service->get($flowId));

    return $service->saveDraft(new FlowDraftUpdate($flowId, $configuration, $current->versionNumber, $current->checksum, $actor, new DateTimeImmutable($at)));
}

it('publishes a saved draft over a live flow in one step, and the flow stays published', function () {
    [$service, , $flowId] = liveFlowFixture();
    saveLiveDraft($service, $flowId, liveFlowConfiguration('Work email'), 12, '2026-10-21T09:00:00+00:00');

    $published = $service->publish(new FlowTransition($flowId, 2, 12, new DateTimeImmutable('2026-10-21T10:00:00+00:00')));

    expect($published->state)->toBe(Flow::STATE_PUBLISHED)
        ->and($published->publishedVersion)->toBe(2)
        ->and($published->publishedAt?->format(DATE_ATOM))->toBe('2026-10-21T10:00:00+00:00')
        ->and(array_map(static fn ($publication): array => $publication->toArray(), $published->publications))->toBe([
            ['version' => 1, 'by' => 11, 'at' => '2026-10-20T09:00:00+00:00'],
            ['version' => 2, 'by' => 12, 'at' => '2026-10-21T10:00:00+00:00'],
        ]);
});

it('keeps the published date when a draft is only saved on a live flow', function () {
    [$service, , $flowId] = liveFlowFixture();

    saveLiveDraft($service, $flowId, liveFlowConfiguration('Work email'), 12, '2026-10-21T09:00:00+00:00');

    expect($service->get($flowId)->publishedAt?->format(DATE_ATOM))->toBe('2026-10-20T09:00:00+00:00');
});

it('refuses to publish a live flow that has nothing new', function () {
    [$service, , $flowId] = liveFlowFixture();

    expect(fn () => $service->publish(new FlowTransition($flowId, 1, 11, new DateTimeImmutable())))
        ->toThrow(DomainException::class, 'Live version is up to date.');
});

it('refuses to publish from an editor whose draft is out of date, and publishes nothing', function () {
    [$service, , $flowId] = liveFlowFixture();
    saveLiveDraft($service, $flowId, liveFlowConfiguration('Work email'), 12, '2026-10-21T09:00:00+00:00');

    expect(fn () => $service->publish(new FlowTransition($flowId, 1, 11, new DateTimeImmutable())))
        ->toThrow(FlowConflictException::class, 'This flow changed since you opened it. Reload to see the latest draft.')
        ->and($service->get($flowId)->publishedVersion)->toBe(1);
});

it('copies an older version into the draft as a new version without publishing it', function () {
    [$service, , $flowId] = liveFlowFixture();
    saveLiveDraft($service, $flowId, liveFlowConfiguration('Work email'), 12, '2026-10-21T09:00:00+00:00');
    $service->publish(new FlowTransition($flowId, 2, 12, new DateTimeImmutable('2026-10-21T10:00:00+00:00')));
    $current = $service->currentVersion($service->get($flowId));

    $copy = $service->copyIntoDraft(1, new FlowDraftUpdate(
        $flowId,
        liveFlowConfiguration('ignored'),
        $current->versionNumber,
        $current->checksum,
        11,
        new DateTimeImmutable('2026-10-22T09:00:00+00:00'),
    ));

    expect($copy->versionNumber)->toBe(3)
        ->and($copy->configuration->schema[0]['label'])->toBe('Email')
        ->and($service->get($flowId)->publishedVersion)->toBe(2);
});

it('judges a visitor by the version their page carried when it was live in the last day', function () {
    [$service, , $flowId] = liveFlowFixture();
    saveLiveDraft($service, $flowId, liveFlowConfiguration('Work email', ['captcha' => 'on']), 12, '2026-10-21T09:00:00+00:00');
    $service->publish(new FlowTransition($flowId, 2, 12, new DateTimeImmutable('2026-10-21T10:00:00+00:00')));
    $flow = $service->get($flowId);

    $soon = $service->versionForVisitor($flow, 1, new DateTimeImmutable('2026-10-21T10:05:00+00:00'));
    $late = $service->versionForVisitor($flow, 1, new DateTimeImmutable('2026-10-22T10:05:00+00:00'));
    $unknown = $service->versionForVisitor($flow, 7, new DateTimeImmutable('2026-10-21T10:05:00+00:00'));

    expect($soon->versionNumber)->toBe(1)
        ->and($soon->configuration->schema[0]['label'])->toBe('Email')
        ->and($soon->configuration->protection)->toBe(['captcha' => 'on'])
        ->and($late->versionNumber)->toBe(2)
        ->and($unknown->versionNumber)->toBe(2);
});

it('lists the live version of a flow published before publications were recorded, with no author', function () {
    $at = new DateTimeImmutable('2026-01-01T00:00:00+00:00');
    $flow = new Flow(
        id: 3,
        uuid: '1d5d3c2b-8a8f-4b4e-9c4f-0e2a7b6c5d4e',
        slug: 'old',
        name: 'Old',
        description: '',
        state: Flow::STATE_PUBLISHED,
        ownerId: 1,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 4,
        publishedVersion: 4,
        testMode: false,
        createdBy: 1,
        updatedBy: 1,
        createdAt: $at,
        updatedAt: $at,
        publishedAt: $at,
    );

    expect(array_map(static fn ($publication): array => $publication->toArray(), $flow->publicationHistory()))
        ->toBe([['version' => 4, 'by' => 0, 'at' => '2026-01-01T00:00:00+00:00']]);
});
