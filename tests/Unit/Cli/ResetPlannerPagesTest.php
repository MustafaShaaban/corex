<?php

/**
 * Unit tests for how the reset planner treats the pages a kit touched (spec 115, FR-003 to FR-007): what
 * the kit created is removed, what the owner had is restored, emptied or kept, never removed, and the
 * reading settings are put back after the pages. Pure: no WordPress.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Reset\ResetAction;
use Corex\Cli\Reset\ResetInventory;
use Corex\Cli\Reset\ResetPage;
use Corex\Cli\Reset\ResetPlanner;
use Corex\Cli\Reset\ResetRequest;

function planPages(ResetInventory $inventory): array
{
    return (new ResetPlanner())->plan(new ResetRequest(ResetRequest::SOFT), $inventory)->actions;
}

it('gives each page the action its history calls for', function (string $persistedAs, bool $hasRecord, string $kind, string $words) {
    $actions = planPages(new ResetInventory(pages: [new ResetPage(5, 'About', $persistedAs, $hasRecord)]));

    expect($actions)->toHaveCount(1)
        ->and($actions[0]->kind)->toBe($kind)
        ->and($actions[0]->target)->toBe('5')
        ->and($actions[0]->label)->toContain('"About" (#5)')
        ->and($actions[0]->label)->toContain($words);
})->with([
    'created by the kit'         => ['created', false, ResetAction::REMOVE_DEMO, 'created, will be removed'],
    'suffixed copy'              => ['suffixed', false, ResetAction::REMOVE_DEMO, 'created, will be removed'],
    'seeded before dispositions' => ['', false, ResetAction::REMOVE_DEMO, 'created, will be removed'],
    'replaced, recorded'         => ['replaced', true, ResetAction::RESTORE_PAGE, 'replaced, will be restored'],
    'replaced, not recorded'     => ['replaced', false, ResetAction::KEEP_PAGE, 'kept: no earlier version recorded'],
    'adopted, recorded'          => ['adopted', true, ResetAction::RESTORE_PAGE, 'adopted, will be emptied'],
    'adopted, not recorded'      => ['adopted', false, ResetAction::EMPTY_PAGE, 'adopted, will be emptied'],
]);

it('says a restored page keeps the edits made since in its revisions', function (string $persistedAs) {
    $actions = planPages(new ResetInventory(pages: [new ResetPage(5, 'About', $persistedAs, true)]));

    expect($actions[0]->label)->toEndWith('(edits made since stay in its revisions).');
})->with(['replaced', 'adopted']);

it('never plans the removal of a page the owner had', function () {
    $actions = planPages(new ResetInventory(pages: [
        new ResetPage(1, 'Home', 'created', false),
        new ResetPage(2, 'About', 'replaced', true),
        new ResetPage(3, 'Team', 'replaced', false),
        new ResetPage(4, 'Contact', 'adopted', true),
        new ResetPage(6, 'Blog', 'adopted', false),
    ]));

    $removed = array_values(array_filter($actions, static fn (ResetAction $a): bool => $a->kind === ResetAction::REMOVE_DEMO));

    expect(array_map(static fn (ResetAction $a): string => $a->target, $removed))->toBe(['1']);
});

it('puts the reading settings back after the pages and before the options go', function () {
    $actions = planPages(new ResetInventory(
        optionKeys: ['corex_kit_reading_before'],
        pages: [new ResetPage(2, 'About', 'replaced', true)],
        hasReadingRecord: true,
    ));

    expect(array_map(static fn (ResetAction $a): string => $a->kind, $actions))->toBe([
        ResetAction::RESTORE_PAGE,
        ResetAction::RESTORE_READING,
        ResetAction::DELETE_OPTION,
    ])->and($actions[1]->label)->toContain('Front-page settings');
});

it('lists the setup demo page once when a kit also tracks it', function () {
    $actions = planPages(new ResetInventory(demoPageId: 9, pages: [new ResetPage(9, 'Home', 'created', false)]));

    expect($actions)->toHaveCount(1);
});
