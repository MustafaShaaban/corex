<?php

/**
 * A flow's availability, its status and the notice a visitor reads (spec 122, US-VIS-01, US-VIS-02).
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowAlternative;
use Corex\Forms\Flow\FlowAvailability;
use Corex\Forms\Flow\FlowNoticeText;
use Corex\Forms\Flow\FlowPublication;
use Corex\Forms\Flow\FlowResponseCounter;
use Corex\Forms\Flow\FlowStatus;
use Corex\Forms\Flow\FlowStatusResolver;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $one, string $many, int $count): string => $count === 1 ? $one : $many);
    Functions\when('get_option')->alias(static fn (string $name): string => $name === 'date_format' ? 'j F Y' : 'H:i');
    Functions\when('wp_date')->alias(static fn (string $format, int $timestamp): string => gmdate($format, $timestamp));
    Functions\when('apply_filters')->returnArg(2);
    Functions\when('do_action')->justReturn(null);
});

function availabilityFlow(string $state, FlowAvailability $availability): Flow
{
    $at = new DateTimeImmutable('2026-10-01T09:00:00+00:00');

    return new Flow(
        id: 4,
        uuid: '6f1c2b8e-9d4a-4c7e-8a1b-2f3e4d5c6b7a',
        slug: 'apply',
        name: 'Apply',
        description: '',
        state: $state,
        ownerId: 2,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: $state === Flow::STATE_DRAFT ? 0 : 1,
        testMode: false,
        createdBy: 2,
        updatedBy: 2,
        createdAt: $at,
        updatedAt: $at,
        publishedAt: $state === Flow::STATE_DRAFT ? null : $at,
        closedAt: $state === Flow::STATE_CLOSED ? $at : null,
        availability: $availability,
    );
}

function countingResponses(int $count): FlowResponseCounter
{
    return new class($count) implements FlowResponseCounter {
        public int $asked = 0;

        public function __construct(private int $count)
        {
        }

        public function responses(int $flowId): int
        {
            $this->asked++;

            return $this->count;
        }
    };
}

it('keeps its dates as instants in UTC and reads back what it stored', function () {
    $stored = FlowAvailability::fromArray([
        'opens_at' => '2026-11-01T09:00:00+02:00',
        'closes_at' => '',
        'max_responses' => '200',
        'closed_message' => ' Applications reopen in March ',
        'alternative' => ['type' => 'email', 'email' => 'jobs@example.test'],
    ]);

    expect($stored->opensAt?->format(DATE_ATOM))->toBe('2026-11-01T07:00:00+00:00')
        ->and($stored->closesAt)->toBeNull()
        ->and($stored->maxResponses)->toBe(200)
        ->and($stored->closedMessage)->toBe('Applications reopen in March')
        ->and(FlowAvailability::fromArray($stored->toArray()))->toEqual($stored);
});

it('refuses dates out of order, a link that is no web address and an email that is no address', function (array $input, string $reason) {
    expect(fn () => FlowAvailability::fromArray($input)->assertValid())->toThrow(DomainException::class, $reason);
})->with([
    'closing before opening' => [['opens_at' => '2026-11-02T00:00:00Z', 'closes_at' => '2026-11-01T00:00:00Z'], 'The closing date must be after the opening date.'],
    'a link without a scheme' => [['alternative' => ['type' => 'link', 'label' => 'Call us', 'url' => 'example.test']], 'The alternative link must be a web address'],
    'a link without a label' => [['alternative' => ['type' => 'link', 'url' => 'https://example.test']], 'The alternative link needs a label.'],
    'an email that is not one' => [['alternative' => ['type' => 'email', 'email' => 'jobs']], 'The alternative email must be an email address.'],
]);

it('reads stored publications and leaves out an entry it cannot read', function () {
    $publications = FlowPublication::listFrom([
        ['version' => 1, 'by' => 2, 'at' => '2026-10-01T09:00:00+00:00'],
        ['version' => 0, 'by' => 2, 'at' => '2026-10-02T09:00:00+00:00'],
        'not an entry',
        ['version' => 3, 'by' => 5, 'at' => '2026-10-03T09:00:00+00:00'],
    ]);

    expect(array_map(static fn (FlowPublication $publication): int => $publication->versionNumber, $publications))->toBe([1, 3]);
});

it('works out the status from the stored state, the dates and the cap', function (string $state, array $availability, int $responses, string $status, string $reason) {
    $resolver = new FlowStatusResolver(countingResponses($responses));
    $now = new DateTimeImmutable('2026-10-23T12:00:00+00:00');

    $resolved = $resolver->at(availabilityFlow($state, FlowAvailability::fromArray($availability)), $now);

    expect($resolved->state)->toBe($status)->and($resolved->reason)->toBe($reason);
})->with([
    'a draft' => [Flow::STATE_DRAFT, [], 0, FlowStatus::DRAFT, ''],
    'closed by hand' => [Flow::STATE_CLOSED, [], 0, FlowStatus::CLOSED, FlowStatus::BY_HAND],
    'published with nothing set' => [Flow::STATE_PUBLISHED, [], 0, FlowStatus::OPEN, ''],
    'before its open date' => [Flow::STATE_PUBLISHED, ['opens_at' => '2026-10-24T09:00:00Z'], 0, FlowStatus::SCHEDULED, FlowStatus::BY_DATE],
    'after its close date' => [Flow::STATE_PUBLISHED, ['closes_at' => '2026-10-23T11:59:00Z'], 0, FlowStatus::EXPIRED, FlowStatus::BY_DATE],
    'one under its cap' => [Flow::STATE_PUBLISHED, ['max_responses' => 3], 2, FlowStatus::OPEN, ''],
    'at its cap' => [Flow::STATE_PUBLISHED, ['max_responses' => 3], 3, FlowStatus::EXPIRED, FlowStatus::BY_CAP],
]);

it('counts responses only for a flow with a cap', function () {
    $counter = countingResponses(10);

    (new FlowStatusResolver($counter))->at(availabilityFlow(Flow::STATE_PUBLISHED, new FlowAvailability()), new DateTimeImmutable());

    expect($counter->asked)->toBe(0);
});

it('tells a visitor the form is closed, in the owner words when there are some, with the alternative', function () {
    $text = new FlowNoticeText();
    $alternative = new FlowAlternative(FlowAlternative::EMAIL, 'Email us', '', 'jobs@example.test');

    $default = $text->notice(availabilityFlow(Flow::STATE_CLOSED, new FlowAvailability(alternative: $alternative)), new FlowStatus(FlowStatus::CLOSED));
    $owners = $text->notice(
        availabilityFlow(Flow::STATE_CLOSED, new FlowAvailability(closedMessage: 'Applications reopen in March')),
        new FlowStatus(FlowStatus::CLOSED),
    );

    expect($default?->toArray())->toBe([
        'title' => 'Closed',
        'message' => 'This form is closed.',
        'alternative' => ['type' => 'email', 'label' => 'Email us', 'url' => 'mailto:jobs@example.test'],
    ])
        ->and($owners?->message)->toBe('Applications reopen in March')
        ->and($owners?->link()['url'])->toBe('');
});

it('says when a scheduled form opens, in the site formats', function () {
    $opens = new DateTimeImmutable('2026-11-01T09:00:00+00:00');

    $notice = (new FlowNoticeText())->notice(
        availabilityFlow(Flow::STATE_PUBLISHED, new FlowAvailability(opensAt: $opens)),
        new FlowStatus(FlowStatus::SCHEDULED, FlowStatus::BY_DATE, $opens),
    );

    expect($notice?->message)->toBe('This form opens on 1 November 2026 at 09:00.');
});

it('gives the list a reason for a flow that expired by date or by its cap, and none for an open one', function () {
    $text = new FlowNoticeText();
    $closed = new DateTimeImmutable('2026-10-20T17:00:00+00:00');

    expect($text->reason(new FlowStatus(FlowStatus::EXPIRED, FlowStatus::BY_DATE, $closed)))->toBe('Closed on 20 October 2026 at 17:00')
        ->and($text->reason(new FlowStatus(FlowStatus::EXPIRED, FlowStatus::BY_CAP, null, 200)))->toBe('Reached 200 responses')
        ->and($text->reason(new FlowStatus(FlowStatus::OPEN)))->toBe('');
});

it('shows nothing for a draft or an open flow', function () {
    $text = new FlowNoticeText();
    $flow = availabilityFlow(Flow::STATE_PUBLISHED, new FlowAvailability());

    expect($text->notice($flow, new FlowStatus(FlowStatus::OPEN)))->toBeNull()
        ->and($text->notice($flow, new FlowStatus(FlowStatus::DRAFT)))->toBeNull();
});
