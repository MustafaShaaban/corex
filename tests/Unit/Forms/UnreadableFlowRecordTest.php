<?php

/**
 * One stored flow that cannot be read does not take the others with it.
 *
 * A flow is a post and three meta rows, and a version is another. Nothing makes those writes one
 * write, so a store can hold a flow whose payload never arrived, or a flow pointing at a draft
 * that was never stored. Every list of flows read all of them or none.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowConfiguration;
use Corex\Forms\Flow\FlowConfigurationValidator;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Flow\FlowService;
use Corex\Forms\Flow\FlowStore;
use Corex\Forms\Flow\FlowVersion;
use Corex\Forms\Flow\UnreadableFlowRecord;
use Corex\Forms\Schema\FieldTypeRegistry;
use Corex\Forms\Success\SuccessStateRegistry;
use Corex\Forms\Validation\RuleRegistry;
use Corex\Support\BootLogger;
use Corex\Tests\Fixtures\Forms\InMemoryFlowStore;

beforeEach(function () {
    Functions\when('__')->returnArg();

    $this->store = new InMemoryFlowStore();
    $this->logger = new BootLogger(debug: false);
    $this->repository = new FlowRepository($this->store, $this->logger);
});

/** A complete stored flow with its first draft, as `FlowService::create()` leaves one. */
function storedFlow(FlowRepository $repository, string $slug): Flow
{
    $at = new DateTimeImmutable('2026-07-04T08:00:00+00:00');
    $flow = $repository->save(new Flow(
        id: 0,
        uuid: '8cb9b4cb-5103-4e3d-9dde-58fac287ca26',
        slug: $slug,
        name: 'Readable ' . $slug,
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
    $repository->appendVersion(new FlowVersion(
        id: 0,
        flowId: $flow->id,
        versionNumber: 1,
        configuration: new FlowConfiguration([], [], [], [], [], []),
        createdBy: 11,
        createdAt: $at,
    ));

    return $flow;
}

/** The payload `storedFlow()` writes, for a record a test then damages in one place. */
function storedFlowPayload(): array
{
    return [
        'uuid' => '8cb9b4cb-5103-4e3d-9dde-58fac287ca26',
        'state' => Flow::STATE_DRAFT,
        'owner_id' => 11,
        'current_draft_version' => 1,
        'created_by' => 11,
        'updated_by' => 11,
        'created_at' => '2026-07-04T08:00:00+00:00',
        'updated_at' => '2026-07-04T08:00:00+00:00',
    ];
}

function flowListingService(FlowRepository $repository): FlowService
{
    return new FlowService($repository, new FlowConfigurationValidator(
        new FieldTypeRegistry(),
        new RuleRegistry(),
        new SuccessStateRegistry(),
    ));
}

dataset('payloads that cannot be read', [
    'the payload never arrived' => [[]],
    'a date that cannot be parsed' => [['created_at' => 'the day before yesterday-ish'] + storedFlowPayload()],
    'a state no flow can be in' => [['state' => 'archived'] + storedFlowPayload()],
]);

it('lists the flows that can be read, and says which record it left out', function (array $payload) {
    $readable = storedFlow($this->repository, 'readable');
    $damaged = $this->store->create('flow', 'damaged', 'Damaged', 0, $payload);

    $listed = $this->repository->all();

    expect(array_map(static fn (Flow $flow): int => $flow->id, $listed))->toBe([$readable->id])
        ->and($this->logger->messages())->toHaveCount(1)
        ->and($this->logger->messages()[0]['level'])->toBe('warning')
        ->and($this->logger->messages()[0]['message'])->toContain('record ' . $damaged);
})->with('payloads that cannot be read');

it('refuses to read that record on its own, naming it', function (array $payload) {
    $damaged = $this->store->create('flow', 'damaged', 'Damaged', 0, $payload);

    expect(fn () => $this->repository->find($damaged))
        ->toThrow(UnreadableFlowRecord::class, 'record ' . $damaged);
})->with('payloads that cannot be read');

it('never skips a version it cannot read, so the number it holds cannot be given out twice', function () {
    $flow = storedFlow($this->repository, 'readable');
    // A second version whose payload never arrived: it has no number to compare with.
    $this->store->create('flow_version', 'flow-' . $flow->id . '-v2', 'Flow version 2', $flow->id, []);

    expect(fn () => $this->repository->versions($flow->id))->toThrow(UnreadableFlowRecord::class)
        ->and(fn () => $this->repository->appendVersion(new FlowVersion(
            id: 0,
            flowId: $flow->id,
            versionNumber: 2,
            configuration: new FlowConfiguration([], [], [], [], [], []),
            createdBy: 11,
            createdAt: new DateTimeImmutable('2026-07-04T09:00:00+00:00'),
        )))->toThrow(UnreadableFlowRecord::class)
        ->and($this->store->all('flow_version', $flow->id))->toHaveCount(2);
});

it('does not take a fault in the code for a record it cannot read', function () {
    // A store that hands back something no store may: the name is not a string. That is a
    // TypeError, which says the code is wrong, and it has to stay as loud as it was. The 500 the
    // browser job met on 2026-10-08 (run 37746210110) was a TypeError PHP raised from its own
    // opcode cache; left out and logged as a warning, it would have read as an empty list.
    $faulty = new class implements FlowStore {
        public function create(string $type, string $slug, string $name, int $parentId, array $payload): int
        {
            return 0;
        }

        public function update(int $id, string $name, array $payload): bool
        {
            return false;
        }

        public function find(int $id): ?array
        {
            return null;
        }

        public function findBySlug(string $type, string $slug): ?array
        {
            return null;
        }

        public function all(string $type, ?int $parentId = null): array
        {
            return [['id' => 1, 'type' => 'flow', 'slug' => 'a', 'name' => null, 'parentId' => 0, 'payload' => storedFlowPayload()]];
        }
    };

    expect(fn () => (new FlowRepository($faulty, $this->logger))->all())->toThrow(TypeError::class)
        ->and($this->logger->messages())->toBe([]);
});

it('lists a flow beside its draft, and leaves out one whose draft was never stored', function () {
    $readable = storedFlow($this->repository, 'readable');
    // Saved, and then nothing: the request that would have stored its first draft did not.
    $draftless = $this->store->create('flow', 'draftless', 'Draftless', 0, storedFlowPayload());
    $service = flowListingService($this->repository);

    $listing = $service->listing('', '');

    expect($listing)->toHaveCount(1)
        ->and($listing[0]['flow']->id)->toBe($readable->id)
        ->and($listing[0]['version']->versionNumber)->toBe(1)
        ->and($this->logger->messages())->toHaveCount(1)
        ->and($this->logger->messages()[0]['message'])->toContain('record ' . $draftless);
});

it('narrows the listing by a search in any case, and by state, as the list always has', function () {
    storedFlow($this->repository, 'contact-sales');
    storedFlow($this->repository, 'newsletter');
    $service = flowListingService($this->repository);

    $slugs = static fn (array $listing): array => array_map(
        static fn (array $entry): string => $entry['flow']->slug,
        $listing,
    );

    expect($slugs($service->listing('SALES', '')))->toBe(['contact-sales'])
        ->and($slugs($service->listing('', Flow::STATE_DRAFT)))->toBe(['contact-sales', 'newsletter'])
        ->and($slugs($service->listing('', Flow::STATE_PUBLISHED)))->toBe([]);
});
