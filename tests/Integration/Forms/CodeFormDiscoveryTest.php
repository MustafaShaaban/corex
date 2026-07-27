<?php

/**
 * Code-registered forms are discovered by the framework, not by the site (spec 074, FR-1.5).
 *
 * The downstream case this pins is Perego's: a site registers its forms exactly as documented —
 * `FormRegistry::register(new SomeForm())` — and expects them to show up in CoreX's own screens.
 * Before the catalog it had to add a `corex_submission_filter_options` hook on every site to make
 * that happen, because the filter list was built from the flow table alone. Every test here runs
 * with **no** filter hook registered; that is the point.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Config\Forms\FlowFilterOptions;
use Corex\Forms\Catalog\FormCatalog;
use Corex\Forms\Catalog\FormCatalogProvider;
use Corex\Forms\Catalog\FormSource;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;

/** A form declared in code the way a downstream site declares one. */
function pereGoStyleForm(string $slug): Form
{
    return new class ($slug) extends Form {
        public function __construct(string $slug)
        {
            $this->slug = $slug;
        }

        public function fields(): array
        {
            return [
                'full_name' => ['type' => 'text', 'rules' => ['required', 'max:120'], 'label' => 'Full name'],
                'email'     => ['type' => 'email', 'rules' => ['required', 'email'], 'label' => 'Email'],
                'budget'    => ['type' => 'text', 'rules' => [], 'label' => 'Budget'],
            ];
        }
    };
}

/** Register a form for the duration of one test, then put the registry back as it was. */
function withRegisteredForm(string $slug, callable $assert): void
{
    $registry = \Corex\Boot::app()->container()->make(FormRegistry::class);
    $before   = $registry->all();

    $registry->register(pereGoStyleForm($slug));

    try {
        $assert();
    } finally {
        // FormRegistry is a keyed map with no removal, so restore it by rebuilding from the
        // snapshot: leaving a test form behind would leak into every later test in the suite.
        $fresh = new FormRegistry();
        foreach ($before as $form) {
            $fresh->register($form);
        }
        \Corex\Boot::app()->container()->singleton(FormRegistry::class, static fn (): FormRegistry => $fresh);
    }
}

it('lists the framework contact form, which is itself registered in code', function () {
    // ContactForm is a FormRegistry form, not a flow. If the catalog only read the flow table this
    // would be empty — which is exactly the defect this spec closes.
    $catalog = \Corex\Boot::app()->container()->make(FormCatalog::class);
    $contact = $catalog->find('contact');

    expect($contact)->not->toBeNull()
        ->and($contact->source)->toBe(FormSource::CODE_FORM)
        ->and($contact->flowId)->toBeNull()
        ->and($contact->editableInBuilder)->toBeFalse()
        ->and($contact->fieldCount())->toBeGreaterThan(0);
});

it('discovers a newly registered code form with no filter hook at all', function () {
    withRegisteredForm('perego-enquiry', function (): void {
        $catalog = new FormCatalog(
            \Corex\Boot::app()->container()->make(\Corex\Forms\Flow\FlowRepository::class),
            \Corex\Boot::app()->container()->make(FormRegistry::class),
        );

        $entry = $catalog->find('perego-enquiry');

        expect($entry)->not->toBeNull()
            ->and($entry->source)->toBe(FormSource::CODE_FORM)
            ->and($entry->label)->toBe('Perego Enquiry')
            ->and($entry->fieldCount())->toBe(3)
            ->and($entry->validationSummary())->toContain('email: required, email');
    });
});

it('offers that code form in the submissions and records filters with no filter hook', function () {
    expect(has_filter('corex_submission_filter_options'))->toBeFalse();

    withRegisteredForm('perego-enquiry', function (): void {
        $options = (new FlowFilterOptions(\Corex\Boot::app()->container()))->all();
        $match   = array_values(array_filter($options, static fn (array $o): bool => $o['slug'] === 'perego-enquiry'));

        expect($match)->toHaveCount(1)
            // id 0 is the contract both screens read as "no flow row — match by corex_form_slug".
            ->and($match[0]['id'])->toBe(0)
            ->and($match[0]['name'])->toBe('Perego Enquiry');
    });
});

it('still honours a site that injects its own entry through the legacy filter', function () {
    $inject = static function (array $options): array {
        $options[] = ['id' => 0, 'name' => 'Legacy Injected', 'slug' => 'legacy-injected'];

        return $options;
    };

    add_filter('corex_submission_filter_options', $inject);

    try {
        $options = (new FlowFilterOptions(\Corex\Boot::app()->container()))->all();
    } finally {
        remove_filter('corex_submission_filter_options', $inject);
    }

    $match = array_values(array_filter($options, static fn (array $o): bool => $o['slug'] === 'legacy-injected'));

    expect($match)->toHaveCount(1);
});

it('does not list a form twice when a site still injects one the catalog now finds', function () {
    // The upgrade path: a site added the hook before discovery existed and has not removed it.
    // Both sources now yield `contact`, and the person must still see one row.
    $inject = static function (array $options): array {
        $options[] = ['id' => 0, 'name' => 'Contact (site copy)', 'slug' => 'contact'];

        return $options;
    };

    add_filter('corex_submission_filter_options', $inject);

    try {
        $options = (new FlowFilterOptions(\Corex\Boot::app()->container()))->all();
    } finally {
        remove_filter('corex_submission_filter_options', $inject);
    }

    $match = array_values(array_filter($options, static fn (array $o): bool => $o['slug'] === 'contact'));

    expect($match)->toHaveCount(1)
        // The catalog's entry wins: it ran first and carries the real definition.
        ->and($match[0]['name'])->not->toBe('Contact (site copy)');
});

it('lets a third-party module contribute forms through the provider seam', function () {
    $catalog = new FormCatalog(
        \Corex\Boot::app()->container()->make(\Corex\Forms\Flow\FlowRepository::class),
        \Corex\Boot::app()->container()->make(FormRegistry::class),
    );

    $catalog->registerProvider(new class () implements FormCatalogProvider {
        public function formCatalogEntries(): array
        {
            return [['slug' => 'partner-signup', 'label' => 'Partner signup', 'field_count' => 4]];
        }
    });

    $entry = $catalog->find('partner-signup');

    expect($entry)->not->toBeNull()
        ->and($entry->source)->toBe(FormSource::EXTERNAL)
        ->and($entry->fieldCount())->toBe(4);
});

it('counts real submissions per form through the WordPress boundary', function () {
    $counts = (new \Corex\Config\Forms\WpSubmissionCounts())->perFormSlug();

    expect($counts)->toBeArray();

    foreach ($counts as $slug => $total) {
        expect($slug)->toBeString()->not->toBe('')
            ->and($total)->toBeInt()->toBeGreaterThanOrEqual(0);
    }
});
