<?php

/**
 * The unified form catalog (spec 074, FR-1).
 *
 * CoreX has two kinds of form — visual flows in the database and forms registered in code through
 * FormRegistry — and until now every consumer built its own list from the flows alone. These tests
 * pin the merge: both kinds present, one deterministic winner per slug, and untrusted third-party
 * entries normalised or dropped rather than rendered as broken controls.
 *
 * @package Corex\Tests\Unit\Forms
 */

declare(strict_types=1);

use Corex\Forms\Catalog\FormCatalog;
use Corex\Forms\Catalog\FormCatalogEntry;
use Corex\Forms\Catalog\FormCatalogProvider;
use Corex\Forms\Catalog\FormSource;
use Corex\Forms\Catalog\SubmissionCounts;
use Corex\Forms\Flow\Flow;
use Corex\Forms\Flow\FlowRepository;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;
use Corex\Tests\Fixtures\Forms\InMemoryFlowStore;

function catalogFlow(string $slug, string $name, string $state = Flow::STATE_PUBLISHED): Flow
{
    return new Flow(
        id: 0,
        uuid: '11111111-1111-4111-8111-111111111111',
        slug: $slug,
        name: $name,
        description: '',
        state: $state,
        ownerId: 1,
        placementType: Flow::PLACEMENT_NONE,
        placementId: null,
        currentDraftVersion: 1,
        publishedVersion: $state === Flow::STATE_PUBLISHED ? 1 : 0,
        testMode: false,
        createdBy: 1,
        updatedBy: 1,
        createdAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
        updatedAt: new DateTimeImmutable('2026-01-01T00:00:00+00:00'),
    );
}

function catalogCodeForm(string $slug, array $fields = []): Form
{
    return new class ($slug, $fields) extends Form {
        public function __construct(string $slug, private readonly array $definitions)
        {
            $this->slug = $slug;
        }

        public function fields(): array
        {
            return $this->definitions;
        }
    };
}

/** @param list<Flow> $flows */
function catalogFor(array $flows = [], array $codeForms = [], array $providers = [], ?SubmissionCounts $counts = null): FormCatalog
{
    $repository = new FlowRepository(new InMemoryFlowStore());
    foreach ($flows as $flow) {
        $repository->save($flow);
    }

    $registry = new FormRegistry();
    foreach ($codeForms as $form) {
        $registry->register($form);
    }

    $catalog = new FormCatalog($repository, $registry, $counts);
    foreach ($providers as $provider) {
        $catalog->registerProvider($provider);
    }

    return $catalog;
}

it('lists a code-registered form without any site-specific filter hook', function () {
    $entries = catalogFor(codeForms: [
        catalogCodeForm('contact', [
            'name'  => ['type' => 'text', 'rules' => ['required'], 'label' => 'Name'],
            'email' => ['type' => 'email', 'rules' => ['required', 'email'], 'label' => 'Email'],
        ]),
    ])->all();

    expect($entries)->toHaveCount(1)
        ->and($entries[0])->toBeInstanceOf(FormCatalogEntry::class)
        ->and($entries[0]->slug)->toBe('contact')
        ->and($entries[0]->source)->toBe(FormSource::CODE_FORM)
        ->and($entries[0]->flowId)->toBeNull()
        ->and($entries[0]->fieldCount())->toBe(2)
        ->and($entries[0]->editableInBuilder)->toBeFalse()
        ->and($entries[0]->active)->toBeTrue();
});

it('carries each code form field definition and its validation summary', function () {
    $entry = catalogFor(codeForms: [
        catalogCodeForm('contact', [
            'email' => ['type' => 'email', 'rules' => ['required', 'email'], 'label' => 'Email'],
        ]),
    ])->all()[0];

    expect($entry->fields)->toBe([
        ['key' => 'email', 'label' => 'Email', 'type' => 'email', 'rules' => ['required', 'email']],
    ])
        ->and($entry->validationSummary())->toBe('email: required, email');
});

it('humanises a code form slug when the form declares no label', function () {
    $entry = catalogFor(codeForms: [catalogCodeForm('call-back-request')])->all()[0];

    expect($entry->label)->toBe('Call Back Request');
});

it('merges visual flows and code forms into one catalog sorted by label', function () {
    $entries = catalogFor(
        flows: [catalogFlow('quote', 'Quote request')],
        codeForms: [catalogCodeForm('contact')],
    )->all();

    expect(array_column($entries, 'slug'))->toBe(['contact', 'quote'])
        ->and($entries[1]->source)->toBe(FormSource::VISUAL_FLOW)
        ->and($entries[1]->flowId)->toBeGreaterThan(0)
        ->and($entries[1]->editableInBuilder)->toBeTrue();
});

it('reports an unpublished flow as inactive', function () {
    $entry = catalogFor(flows: [catalogFlow('draft-form', 'Draft form', Flow::STATE_DRAFT)])->all()[0];

    expect($entry->active)->toBeFalse();
});

it('lets the visual flow win when a code form claims the same slug', function () {
    $catalog = catalogFor(
        flows: [catalogFlow('contact', 'Contact flow')],
        codeForms: [catalogCodeForm('contact')],
    );

    expect($catalog->all())->toHaveCount(1)
        ->and($catalog->all()[0]->source)->toBe(FormSource::VISUAL_FLOW)
        ->and($catalog->all()[0]->label)->toBe('Contact flow');
});

it('keeps the losing duplicate as a shadowed entry for diagnostics', function () {
    $catalog = catalogFor(
        flows: [catalogFlow('contact', 'Contact flow')],
        codeForms: [catalogCodeForm('contact')],
    );

    expect($catalog->shadowed())->toHaveCount(1)
        ->and($catalog->shadowed()[0]->source)->toBe(FormSource::CODE_FORM);
});

it('lets a code form win over an external provider claiming the same slug', function () {
    $catalog = catalogFor(
        codeForms: [catalogCodeForm('contact')],
        providers: [new class () implements FormCatalogProvider {
            public function formCatalogEntries(): array
            {
                return [['slug' => 'contact', 'label' => 'Third-party contact']];
            }
        }],
    );

    expect($catalog->all())->toHaveCount(1)
        ->and($catalog->all()[0]->source)->toBe(FormSource::CODE_FORM);
});

it('accepts a well-formed external provider entry', function () {
    $entry = catalogFor(providers: [new class () implements FormCatalogProvider {
        public function formCatalogEntries(): array
        {
            return [[
                'slug' => 'newsletter',
                'label' => 'Newsletter signup',
                'field_count' => 3,
                'active' => true,
            ]];
        }
    }])->all()[0];

    expect($entry->slug)->toBe('newsletter')
        ->and($entry->source)->toBe(FormSource::EXTERNAL)
        ->and($entry->fieldCount())->toBe(3)
        ->and($entry->editableInBuilder)->toBeFalse();
});

it('drops malformed provider entries rather than rendering broken controls', function () {
    $catalog = catalogFor(providers: [new class () implements FormCatalogProvider {
        public function formCatalogEntries(): array
        {
            return [
                'not-an-array',
                ['label' => 'No slug at all'],
                ['slug' => '', 'label' => 'Empty slug'],
                ['slug' => '!!!', 'label' => 'Slug with nothing usable'],
                ['slug' => 'usable', 'label' => 'Usable'],
            ];
        }
    }]);

    expect($catalog->all())->toHaveCount(1)
        ->and($catalog->all()[0]->slug)->toBe('usable');
});

it('survives a provider that throws', function () {
    $catalog = catalogFor(
        codeForms: [catalogCodeForm('contact')],
        providers: [new class () implements FormCatalogProvider {
            public function formCatalogEntries(): array
            {
                throw new RuntimeException('provider exploded');
            }
        }],
    );

    expect($catalog->all())->toHaveCount(1)
        ->and($catalog->all()[0]->slug)->toBe('contact');
});

it('reports the submission count as unavailable rather than guessing zero', function () {
    $entry = catalogFor(codeForms: [catalogCodeForm('contact')])->all()[0];

    expect($entry->submissionCount)->toBeNull()
        ->and($entry->hasSubmissionCount())->toBeFalse();
});

it('uses the counts the boundary supplies, and zero is a real zero', function () {
    $counts = new class () implements SubmissionCounts {
        public function perFormSlug(): array
        {
            return ['contact' => 194];
        }
    };

    $entries = catalogFor(
        flows: [catalogFlow('quote', 'Quote request')],
        codeForms: [catalogCodeForm('contact')],
        counts: $counts,
    )->all();

    expect($entries[0]->submissionCount)->toBe(194)
        ->and($entries[0]->hasSubmissionCount())->toBeTrue()
        ->and($entries[1]->submissionCount)->toBe(0)
        ->and($entries[1]->hasSubmissionCount())->toBeTrue();
});

it('picks up a form registered after the catalog was first read', function () {
    // The catalog is a container singleton and caches its result, so a plugin registering a form
    // on a later hook than the first read would otherwise never appear at all.
    $repository = new Corex\Forms\Flow\FlowRepository(new InMemoryFlowStore());
    $registry   = new FormRegistry();
    $registry->register(catalogCodeForm('contact'));

    $catalog = new FormCatalog($repository, $registry);

    expect($catalog->all())->toHaveCount(1);

    $registry->register(catalogCodeForm('late-arrival'));

    expect($catalog->all())->toHaveCount(2)
        ->and(array_column($catalog->all(), 'slug'))->toContain('late-arrival');
});

it('picks up a provider registered after the catalog was first read', function () {
    $catalog = catalogFor(codeForms: [catalogCodeForm('contact')]);

    expect($catalog->all())->toHaveCount(1);

    $catalog->registerProvider(new class () implements FormCatalogProvider {
        public function formCatalogEntries(): array
        {
            return [['slug' => 'partner', 'label' => 'Partner']];
        }
    });

    expect($catalog->all())->toHaveCount(2);
});

it('is empty, not fatal, when nothing is registered', function () {
    expect(catalogFor()->all())->toBe([])
        ->and(catalogFor()->shadowed())->toBe([]);
});

it('exposes the ability that governs managing a form', function () {
    $entry = catalogFor(codeForms: [catalogCodeForm('contact')])->all()[0];

    expect($entry->capability)->toBe('corex_manage_forms');
});

it('shapes an entry for transport without losing the unavailable count', function () {
    $entry = catalogFor(codeForms: [catalogCodeForm('contact')])->all()[0];

    expect($entry->toArray())->toMatchArray([
        'slug' => 'contact',
        'source' => FormSource::CODE_FORM,
        'flow_id' => null,
        'submission_count' => null,
        'editable_in_builder' => false,
    ]);
});
