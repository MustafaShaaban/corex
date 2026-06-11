<?php

/**
 * Unit tests for the pure reset planner (spec 025 US1/US2/US3: FR-001..004, FR-006/007, SC-005).
 * Pure — no WordPress, no database.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Reset\ResetAction;
use Corex\Cli\Reset\ResetInventory;
use Corex\Cli\Reset\ResetPlanner;
use Corex\Cli\Reset\ResetRequest;

function kinds(array $actions): array
{
    return array_map(static fn (ResetAction $a): string => $a->kind, $actions);
}

it('plans a soft reset as deactivate -> remove-demo -> delete-option, in order', function () {
    $inventory = new ResetInventory(
        addonPlugins: ['corex-ui/corex-ui.php', 'corex-forms/corex-forms.php'],
        optionKeys: ['corex_features_pro', 'corex_setup_demo_seeded'],
        demoPageId: 42,
    );

    $plan = (new ResetPlanner())->plan(new ResetRequest(ResetRequest::SOFT), $inventory);

    expect(kinds($plan->actions))->toBe([
        ResetAction::DEACTIVATE_ADDON,
        ResetAction::DEACTIVATE_ADDON,
        ResetAction::REMOVE_DEMO,
        ResetAction::DELETE_OPTION,
        ResetAction::DELETE_OPTION,
    ])->and($plan->isDestructive())->toBeFalse();
});

it('omits the remove-demo step when nothing was seeded', function () {
    $inventory = new ResetInventory(addonPlugins: ['corex-ui/corex-ui.php'], optionKeys: ['corex_features_pro']);

    $plan = (new ResetPlanner())->plan(new ResetRequest(ResetRequest::SOFT), $inventory);

    expect(kinds($plan->actions))->toBe([ResetAction::DEACTIVATE_ADDON, ResetAction::DELETE_OPTION]);
});

it('reports nothing to reset for an empty footprint', function () {
    $plan = (new ResetPlanner())->plan(new ResetRequest(ResetRequest::SOFT), new ResetInventory());

    expect($plan->isEmpty())->toBeTrue()
        ->and($plan->summary())->toBe('Nothing to reset.');
});

it('plans a full reset as a single destructive db-wipe', function () {
    $plan = (new ResetPlanner())->plan(
        new ResetRequest(ResetRequest::FULL, confirmed: true),
        new ResetInventory(addonPlugins: ['corex-ui/corex-ui.php']),
    );

    expect(kinds($plan->actions))->toBe([ResetAction::DB_WIPE])
        ->and($plan->isDestructive())->toBeTrue();
});
