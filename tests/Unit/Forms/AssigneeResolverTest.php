<?php

/**
 * Routing assigns a real person, team or role, and says why when a target names nobody
 * (spec 122, US-FLW-03, US-FLW-04).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Routing\Assignee;
use Corex\Forms\Routing\AssigneeResolver;
use Corex\Forms\Routing\RoutedRecipients;
use Corex\Forms\Routing\RoutingPerson;
use Corex\Forms\Routing\RoutingService;
use Corex\Forms\Routing\RoutingSubject;
use Corex\Forms\Routing\RoutingTarget;
use Corex\Forms\Routing\RoutingTargetRegistry;
use Corex\Forms\Routing\Teams\Team;
use Corex\Forms\Submission\PageReference;
use Corex\Forms\Submission\Stages\RoutingStage;
use Corex\Forms\Submission\SubmissionPipelineContext;
use Corex\Tests\Fixtures\Forms\FakeRoutingDirectory;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $one, string $many, int $count): string => $count === 1 ? $one : $many);
    Functions\when('sanitize_key')->alias(static fn (string $value): string => strtolower($value));
    Functions\when('is_email')->alias(static fn (string $value): bool => filter_var($value, FILTER_VALIDATE_EMAIL) !== false);
    Functions\when('wp_hash')->alias(static fn (string $data): string => md5('salt' . $data));
});

function routingDirectory(): FakeRoutingDirectory
{
    return new FakeRoutingDirectory(
        people: [
            2 => new RoutingPerson(2, 'Sara Ahmed', 'sara@example.test'),
            3 => new RoutingPerson(3, 'Omar Ali', 'omar@example.test'),
            4 => new RoutingPerson(4, 'Lina Saad', 'lina@example.test'),
            5 => new RoutingPerson(5, 'Nour Haddad', ''),
        ],
        teams: ['sales' => new Team('sales', 'Sales', [3, 4, 5])],
        roles: ['editor' => ['name' => 'Editor', 'members' => [3, 4]]],
        pageAuthors: [40 => 4, 41 => 99],
    );
}

function routedFlow(): Flow
{
    $at = new DateTimeImmutable('2026-10-20T08:00:00+00:00');

    return new Flow(
        id: 9,
        uuid: '1d5d3c2b-8a8f-4b4e-9c4f-0e2a7b6c5d4e',
        slug: 'contact',
        name: 'Contact',
        description: '',
        state: Flow::STATE_PUBLISHED,
        ownerId: 2,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: 1,
        testMode: false,
        createdBy: 2,
        updatedBy: 2,
        createdAt: $at,
        updatedAt: $at,
    );
}

function resolverWith(?RoutingTargetRegistry $extensions = null): AssigneeResolver
{
    return new AssigneeResolver(routingDirectory(), $extensions ?? new RoutingTargetRegistry());
}

it('resolves each target to the person, team or role it names', function (array $target, array $values, ?int $page, array $expected) {
    $resolution = resolverWith()->resolve(
        new RoutingTarget($target['type'], $target['config'] ?? []),
        new RoutingSubject(routedFlow(), $values, $page),
    );

    expect($resolution->assignee?->toArray())->toBe($expected);
})->with([
    'a user' => [['type' => 'user', 'config' => ['value' => '3']], [], null, ['type' => 'user', 'key' => '3']],
    'a team' => [['type' => 'team', 'config' => ['value' => 'sales']], [], null, ['type' => 'team', 'key' => 'sales']],
    'a role' => [['type' => 'role', 'config' => ['value' => 'editor']], [], null, ['type' => 'role', 'key' => 'editor']],
    'the flow owner' => [['type' => 'flow_owner'], [], null, ['type' => 'user', 'key' => '2']],
    'the page owner' => [['type' => 'page_owner'], [], 40, ['type' => 'user', 'key' => '4']],
    'a retired post author, read as the page owner' => [['type' => 'post_author'], [], 40, ['type' => 'user', 'key' => '4']],
    'the owner of the picked value' => [
        ['type' => 'field_value', 'config' => ['value' => 'department', 'owners' => [
            ['value' => 'Sales', 'target' => ['type' => 'team', 'value' => 'sales']],
        ]]],
        ['department' => 'Sales'],
        null,
        ['type' => 'team', 'key' => 'sales'],
    ],
]);

it('says why a target names nobody', function (array $target, array $values, ?int $page, string $reason) {
    $resolution = resolverWith()->resolve(
        new RoutingTarget($target['type'], $target['config'] ?? []),
        new RoutingSubject(routedFlow(), $values, $page),
    );

    expect($resolution->assignee)->toBeNull()->and($resolution->reason)->toBe($reason);
})->with([
    'a deleted user' => [['type' => 'user', 'config' => ['value' => 77]], [], null, 'The person this route names no longer has an account.'],
    'a removed team' => [['type' => 'team', 'config' => ['value' => 'support']], [], null, 'The team "support" no longer exists.'],
    'a page with no author' => [['type' => 'page_owner'], [], 41, 'The page the form was sent from has no author.'],
    'an unknown page' => [['type' => 'page_owner'], [], null, 'The page the form was sent from is not known.'],
    'a value with no owner' => [
        ['type' => 'field_value', 'config' => ['value' => 'department', 'owners' => []]],
        ['department' => 'Other'],
        null,
        'The answer "Other" to "department" has no owner.',
    ],
    'a retired email target' => [['type' => 'email', 'config' => ['value' => 'x@example.test']], [], null, 'This route uses a target CoreX no longer offers.'],
    'an extension that is gone' => [['type' => 'extension', 'config' => ['value' => 'crm']], [], null, 'The routing extension "crm" is not available.'],
]);

it('assigns the owner a registered routing extension returns, and falls back when it returns none', function () {
    $extensions = new RoutingTargetRegistry();
    $extensions->register('by_region', 'By region', static fn (array $submission): ?int => ($submission['values']['region'] ?? '') === 'north' ? 3 : null);

    $north = resolverWith($extensions)->resolve(new RoutingTarget('extension', ['value' => 'by_region']), new RoutingSubject(routedFlow(), ['region' => 'north']));
    $south = resolverWith($extensions)->resolve(new RoutingTarget('extension', ['value' => 'by_region']), new RoutingSubject(routedFlow(), ['region' => 'south']));

    expect($north->assignee?->toArray())->toBe(['type' => 'user', 'key' => '3'])
        ->and($south->reason)->toBe('The routing extension "By region" named nobody.');
});

function routingContext(array $routing, array $metadata = []): SubmissionPipelineContext
{
    $configuration = new FlowConfiguration([['key' => 'department', 'type' => 'select']], [], $routing, [], [], []);

    return new SubmissionPipelineContext(
        routedFlow(),
        new FlowVersion(5, 9, 1, $configuration, 2, new DateTimeImmutable('2026-10-20T08:00:00+00:00')),
        ['department' => 'Other'],
        false,
        null,
        $metadata,
    );
}

function routingStage(): RoutingStage
{
    $directory = routingDirectory();

    return new RoutingStage(new RoutingService(), new AssigneeResolver($directory, new RoutingTargetRegistry()), new RoutedRecipients($directory));
}

it('takes the fallback when the matched target names nobody, and keeps the reason', function () {
    $result = routingStage()->execute(routingContext([
        'rules' => [[
            'uuid' => 'r-1',
            'position' => 10,
            'condition' => ['field' => 'department', 'operator' => 'equals', 'value' => 'Other'],
            'target' => ['type' => 'field_value', 'config' => ['value' => 'department', 'owners' => []]],
        ]],
        'fallback' => ['type' => 'team', 'config' => ['value' => 'sales']],
    ]));
    $routing = $result->context->metadata['routing'];

    expect($routing['assignee'])->toBe(['type' => 'team', 'key' => 'sales', 'label' => 'Sales'])
        ->and($routing['failures'])->toBe([['target' => 'field_value', 'reason' => 'The answer "Other" to "department" has no owner.']]);
});

it('gives the submission to the flow owner when the fallback names nobody too', function () {
    $result = routingStage()->execute(routingContext([
        'rules' => [],
        'fallback' => ['type' => 'page_owner', 'config' => []],
    ], ['source' => ['page_id' => 41]]));
    $routing = $result->context->metadata['routing'];

    expect($routing['assignee'])->toBe(['type' => 'user', 'key' => '2', 'label' => 'Sara Ahmed'])
        ->and(array_column($routing['failures'], 'target'))->toBe(['page_owner'])
        ->and($routing['recipients'])->toBe(['addresses' => ['sara@example.test'], 'note' => '']);
});

it('mails the routed owner: a user, every team member, a role up to its limit', function (Assignee $assignee, array $routing, array $expected) {
    expect((new RoutedRecipients(routingDirectory()))->for($assignee, $routing))->toBe($expected);
})->with([
    'a user' => [Assignee::user(2), [], ['addresses' => ['sara@example.test'], 'note' => '']],
    'a team, skipping a member with no address' => [Assignee::team('sales'), [], ['addresses' => ['omar@example.test', 'lina@example.test'], 'note' => '']],
    'a role under its limit' => [Assignee::role('editor'), ['recipient_limit' => 2], ['addresses' => ['omar@example.test', 'lina@example.test'], 'note' => '']],
    'a role over its limit' => [
        Assignee::role('editor'),
        ['recipient_limit' => 1, 'fallback_recipient' => 'desk@example.test'],
        ['addresses' => ['desk@example.test'], 'note' => 'Routed owner is a role with more than 1 person; sent to desk@example.test'],
    ],
    'a person with no address, to the site administrator' => [
        Assignee::user(5),
        [],
        ['addresses' => ['admin@example.test'], 'note' => 'Routed owner had no address; sent to admin@example.test'],
    ],
]);

it('signs the page a form was printed on and refuses a changed or borrowed value', function () {
    $pages = new PageReference();
    $signed = $pages->sign(40, 9);

    expect($pages->verify($signed, 9))->toBe(40)
        ->and($pages->verify(str_replace('40.', '41.', $signed), 9))->toBeNull()
        ->and($pages->verify($signed, 10))->toBeNull()
        ->and($pages->verify('40', 9))->toBeNull()
        ->and($pages->sign(0, 9))->toBe('');
});
