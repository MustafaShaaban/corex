<?php

/**
 * Which flows bind an Email Studio template, for the variable warnings Email Studio gives
 * (spec 120, story 5): a flow's draft and its published version both count, so a template the
 * live version sends is warned about even when the draft no longer binds it.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowTemplateUsage;
use Corex\Forms\Flow\FlowVersion;
use Corex\Mail\MailTemplateUsage;
use Corex\Support\BootLogger;
use Corex\Tests\Fixtures\Forms\InMemoryFlowStore;

/** @param list<array<string,mixed>> $emailRoutes */
function usageConfiguration(array $emailRoutes): FlowConfiguration
{
    return new FlowConfiguration(
        schema: [['uuid' => 'f-1', 'key' => 'email', 'label' => 'Email', 'type' => 'email']],
        validation: [],
        routing: [],
        emailRoutes: $emailRoutes,
        success: ['type' => 'inline', 'message' => 'Thanks'],
        placementSnapshot: ['type' => 'none'],
    );
}

/** A flow published at version 1, whose draft is version 2. */
function usageRepository(array $publishedRoutes, array $draftRoutes): FlowRepository
{
    $repository = new FlowRepository(new InMemoryFlowStore(), new BootLogger(debug: false));
    $at = new DateTimeImmutable('2026-11-11T08:00:00+00:00');
    $flow = $repository->save(new Flow(
        id: 0,
        uuid: '2e6d4c3b-9a8f-4b4e-9c4f-0e2a7b6c5d4f',
        slug: 'quote',
        name: 'Quote',
        description: '',
        state: Flow::STATE_PUBLISHED,
        ownerId: 1,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 2,
        publishedVersion: 1,
        testMode: false,
        createdBy: 1,
        updatedBy: 1,
        createdAt: $at,
        updatedAt: $at,
    ));
    $repository->appendVersion(new FlowVersion(0, $flow->id, 1, usageConfiguration($publishedRoutes), 1, $at));
    $repository->appendVersion(new FlowVersion(0, $flow->id, 2, usageConfiguration($draftRoutes), 1, $at));

    return $repository;
}

it('counts a template that only the published version of a flow binds', function () {
    $repository = usageRepository(
        [['event' => 'team_notification', 'enabled' => true, 'template_id' => 4, 'recipient' => 'team@example.com']],
        [],
    );

    expect((new FlowTemplateUsage($repository))->emailsUsing(4))->toBe([MailTemplateUsage::FORM]);
});

it('counts a template the draft binds, and none that no version binds', function () {
    $repository = usageRepository(
        [],
        [['event' => 'team_notification', 'enabled' => true, 'template_id' => 4, 'recipient' => 'team@example.com']],
    );

    expect((new FlowTemplateUsage($repository))->emailsUsing(4))->toBe([MailTemplateUsage::FORM])
        ->and((new FlowTemplateUsage($repository))->emailsUsing(9))->toBe([]);
});
