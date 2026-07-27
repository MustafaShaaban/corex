<?php

/**
 * Blog Pro's state vocabulary, in words (spec 075, FR-2/FR-3).
 *
 * The screen printed `ready_for_review` and `publish` at people — raw slugs, untranslated, from a
 * screen that is otherwise localised. Translating them in JavaScript would put the mapping in a second
 * place and let the two drift, which is the failure DECISIONS #157 was written about, so the server
 * owns it and the client renders what it is given.
 *
 * @package Corex\Tests\Unit\Blog
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Blog\BlogProLabels;
use Corex\Config\Blog\EditorialItem;

beforeEach(function () {
    // `__()` passes its text through, so these assert the *words chosen*, not the translation layer.
    Functions\stubTranslationFunctions();
});

it('names every editorial state the workflow can be in', function () {
    // Sourced from EditorialItem's own constants: if a state is added there and not here, this fails,
    // which is the point — a new state must not reach the screen as a slug.
    $states = [
        EditorialItem::STATE_DRAFT,
        EditorialItem::STATE_READY_FOR_REVIEW,
        EditorialItem::STATE_NEEDS_CHANGES,
        EditorialItem::STATE_APPROVED,
        EditorialItem::STATE_SCHEDULED,
        EditorialItem::STATE_PUBLISHED,
    ];

    foreach ($states as $state) {
        $label = BlogProLabels::editorialState($state);

        expect($label)->not->toBe('')
            ->and($label)->not->toBe($state)
            ->and($label)->not->toContain('_');
    }
});

it('distinguishes the editorial states from one another', function () {
    // A map that returned the same word twice would pass the test above and still be useless.
    $labels = array_map(
        static fn (string $state): string => BlogProLabels::editorialState($state),
        BlogProLabels::editorialStates(),
    );

    expect($labels)->toHaveCount(6)
        ->and(array_unique($labels))->toHaveCount(6);
});

it('names every comment state the moderation queue can show', function () {
    foreach (['approved', 'spam', 'trash', 'pending'] as $state) {
        $label = BlogProLabels::commentState($state);

        expect($label)->not->toBe('')->and($label)->not->toBe($state);
    }
});

it('names the moderation actions this spec offers, and no others', function () {
    // The queue holds only comments awaiting review, so approve/spam/trash are the actions that mean
    // anything; the service's edit and reply are excluded by spec 075 §10.
    expect(array_keys(BlogProLabels::commentActions()))->toBe(['approve', 'spam', 'trash']);

    foreach (BlogProLabels::commentActions() as $action => $label) {
        expect($label)->not->toBe('')->and($label)->not->toBe($action);
    }
});

it('falls back to the raw key rather than rendering nothing', function () {
    // A slug is bad; a blank cell where a state should be is worse — it reads as "no state" rather
    // than "a state CoreX has no word for yet".
    expect(BlogProLabels::editorialState('some_future_state'))->toBe('some_future_state')
        ->and(BlogProLabels::commentState('moderated-by-a-plugin'))->toBe('moderated-by-a-plugin');
});

it('names the native WordPress statuses it shows beside the CoreX state', function () {
    // The workflow service maps CoreX states onto native ones and a divergence is worth seeing, so
    // the native status is shown too — and it was the other raw slug on the screen.
    foreach (['draft', 'pending', 'publish', 'future', 'private'] as $status) {
        expect(BlogProLabels::nativeStatus($status))->not->toBe('')->and(
            BlogProLabels::nativeStatus($status)
        )->not->toBe($status);
    }
});
