<?php

/**
 * Integration tests for the form filter's option list.
 *
 * Filtering by a form used to mean knowing its numeric ID (Submissions) or typing its slug exactly
 * (Records). This is the list of real names both screens offer instead.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Config\Forms\FlowFilterOptions;
use Corex\Container\Container;

it('lists real flows with both keys the two screens need', function () {
    $options = \Corex\Boot::app()->container()->make(FlowFilterOptions::class)->all();

    expect($options)->toBeArray();

    if ($options === []) {
        // A site with no forms yet is a legitimate state, and the screens must survive it.
        expect(true)->toBeTrue();

        return;
    }

    foreach ($options as $option) {
        expect($option)->toHaveKeys(['id', 'name', 'slug'])
            ->and($option['id'])->toBeInt()
            // Both keys travel together on purpose: Submissions filters on the flow ID
            // (meta corex_flow_id) and Records on the slug (meta corex_form_slug).
            ->and($option['slug'])->toBeString()->not->toBe('')
            // Never a nameless row — it falls back to the slug.
            ->and($option['name'])->toBeString()->not->toBe('');
    }
});

it('sorts by name so the list is scannable rather than in insertion order', function () {
    $options = (new FlowFilterOptions(\Corex\Boot::app()->container()))->all();
    $names = array_column($options, 'name');
    $sorted = $names;
    usort($sorted, 'strcasecmp');

    expect($names)->toBe($sorted);
});

it('returns an empty list instead of failing when forms is unavailable', function () {
    // Forms is an optional add-on (Principle IX). A real, empty container is the honest stand-in
    // for a site without it: FlowRepository is simply not bound, so resolving it throws exactly as
    // it would there. Both screens must survive that — the filter drops, the screen works.
    expect((new FlowFilterOptions(new Container()))->all())->toBe([]);
});

it('is reachable from the container so the screens can depend on it', function () {
    expect(\Corex\Boot::app()->container()->make(FlowFilterOptions::class))
        ->toBeInstanceOf(FlowFilterOptions::class);
});

it('lets a site add a form that has no flow row (issue #114)', function () {
    // Forms registered in code through FormRegistry have no corex_flow_record, so they never
    // appeared here and their submissions could be listed but not filtered. `id => 0` means
    // "match this by corex_form_slug instead".
    $inject = static function (array $options): array {
        $options[] = ['id' => 0, 'name' => 'Coded Contact', 'slug' => 'coded-contact'];

        return $options;
    };

    add_filter('corex_submission_filter_options', $inject);

    try {
        $options = (new FlowFilterOptions(\Corex\Boot::app()->container()))->all();
    } finally {
        remove_filter('corex_submission_filter_options', $inject);
    }

    $injected = array_values(array_filter($options, static fn (array $o): bool => $o['slug'] === 'coded-contact'));

    expect($injected)->toHaveCount(1)
        ->and($injected[0]['id'])->toBe(0)
        ->and($injected[0]['name'])->toBe('Coded Contact');
});

it('forces injected options into shape rather than trusting the filter', function () {
    // A filter is an open door. An entry with neither a flow id nor a slug cannot be matched by
    // either screen, so it is dropped instead of rendering a row that filters to nothing; a
    // nameless one falls back to its slug rather than rendering blank.
    $inject = static function (array $options): array {
        return [
            ['id' => '7', 'name' => '', 'slug' => 'Needs Sanitizing'],
            ['id' => 0, 'slug' => ''],
            'not-an-array',
        ];
    };

    add_filter('corex_submission_filter_options', $inject);

    try {
        $options = (new FlowFilterOptions(\Corex\Boot::app()->container()))->all();
    } finally {
        remove_filter('corex_submission_filter_options', $inject);
    }

    expect($options)->toHaveCount(1)
        ->and($options[0]['id'])->toBe(7)
        ->and($options[0]['slug'])->toBe('needssanitizing')
        ->and($options[0]['name'])->toBe('needssanitizing');
});
