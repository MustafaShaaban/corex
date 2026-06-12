<?php

/**
 * Unit tests for the pure create/adopt/skip page classifier (spec 041: FR-001, FR-007). No WordPress.
 *
 * @package Corex\Tests\Unit\Provisioning
 */

declare(strict_types=1);

use Corex\Provisioning\PageDisposition;
use Corex\Provisioning\PagePlanner;

function declaredPages(): array
{
    return [
        ['title' => 'Home', 'slug' => 'home', 'content' => '<!-- wp:corex/hero /-->', 'front' => true],
        ['title' => 'About', 'slug' => 'about', 'content' => '<!-- wp:corex/features /-->'],
    ];
}

/** @return array{exists:bool,isEmpty:bool,isKitPlaceholder:bool} */
function signal(bool $exists, bool $isEmpty = false, bool $isKitPlaceholder = false): array
{
    return ['exists' => $exists, 'isEmpty' => $isEmpty, 'isKitPlaceholder' => $isKitPlaceholder];
}

it('creates a page whose slug is absent', function () {
    $plan = (new PagePlanner())->plan(declaredPages(), []);

    expect($plan[0]->action)->toBe(PageDisposition::CREATE)
        ->and($plan[0]->reason)->toBe('slug_absent');
});

it('adopts an existing empty page', function () {
    $plan = (new PagePlanner())->plan(declaredPages(), ['home' => signal(true, isEmpty: true)]);

    expect($plan[0]->action)->toBe(PageDisposition::ADOPT)
        ->and($plan[0]->reason)->toBe('existing_empty');
});

it('adopts an un-populated kit placeholder', function () {
    $plan = (new PagePlanner())->plan(declaredPages(), ['home' => signal(true, isEmpty: true, isKitPlaceholder: true)]);

    expect($plan[0]->action)->toBe(PageDisposition::ADOPT)
        ->and($plan[0]->reason)->toBe('kit_placeholder');
});

it('skips an existing page that has user content', function () {
    $plan = (new PagePlanner())->plan(declaredPages(), ['home' => signal(true, isEmpty: false)]);

    expect($plan[0]->action)->toBe(PageDisposition::SKIP)
        ->and($plan[0]->reason)->toBe('user_content');
});

it('classifies each declared page independently', function () {
    $plan = (new PagePlanner())->plan(declaredPages(), [
        'home'  => signal(true, isEmpty: true),   // adopt
        'about' => signal(true, isEmpty: false),  // skip (user content)
    ]);

    expect(array_map(fn ($d) => $d->action, $plan))->toBe([PageDisposition::ADOPT, PageDisposition::SKIP]);
});
