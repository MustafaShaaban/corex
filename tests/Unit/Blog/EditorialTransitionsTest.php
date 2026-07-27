<?php

/**
 * What the editorial panel may offer (spec 075, FR-2).
 *
 * These describe the workflow service as it actually is, which is worth stating plainly because it is
 * not what "editorial workflow" usually implies: `EditorialWorkflowService::transition()` has **no
 * transition graph**. It accepts any of the six states from any other and maps each to a native
 * WordPress status. Its one real constraint is that `scheduled` without a timestamp throws.
 *
 * So the panel offers every state but the current one. Inventing a graph here would be new domain
 * logic, which spec 075 §10 forbids — the UI must not imply a rule the service does not enforce.
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Blog\EditorialItem;
use Corex\Config\Blog\EditorialTransitions;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

it('offers every state except the one the post is already in', function () {
    $offered = EditorialTransitions::from(EditorialItem::STATE_DRAFT);

    expect(array_column($offered, 'key'))
        ->toBe([
            EditorialItem::STATE_READY_FOR_REVIEW,
            EditorialItem::STATE_NEEDS_CHANGES,
            EditorialItem::STATE_APPROVED,
            EditorialItem::STATE_SCHEDULED,
            EditorialItem::STATE_PUBLISHED,
        ]);
});

it('never offers a move to where the post already is', function () {
    foreach (EditorialTransitions::states() as $state) {
        expect(array_column(EditorialTransitions::from($state), 'key'))
            ->not->toContain($state)
            ->and(EditorialTransitions::from($state))->toHaveCount(5);
    }
});

it('flags the one transition that needs more than a click', function () {
    // `scheduledStatus()` throws without a timestamp, so the panel has to know to require the field
    // rather than let the request fail at the server.
    $offered = EditorialTransitions::from(EditorialItem::STATE_DRAFT);
    $byKey = array_column($offered, 'requires_schedule', 'key');

    expect($byKey[EditorialItem::STATE_SCHEDULED])->toBeTrue()
        ->and($byKey[EditorialItem::STATE_PUBLISHED])->toBeFalse()
        ->and($byKey[EditorialItem::STATE_APPROVED])->toBeFalse();
});

it('offers everything when it does not recognise the current state', function () {
    // A post whose stored state came from an older version, or from a plugin. Offering all six is the
    // recoverable answer; offering none would strand the post with no way out.
    expect(EditorialTransitions::from('some_future_state'))->toHaveCount(6);
});

it('carries a label with every option so the panel never renders a slug', function () {
    foreach (EditorialTransitions::from(EditorialItem::STATE_APPROVED) as $option) {
        expect($option['label'])->not->toBe('')->and($option['label'])->not->toBe($option['key']);
    }
});
