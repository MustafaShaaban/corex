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
