<?php

/**
 * Unit tests for what a notification's call-to-action carries, and who is offered it
 * (spec 087, FR-010 / FR-011 / FR-012 / FR-014).
 *
 * Two defects are pinned here, both of which lived behind passing tests.
 *
 * The first: the payload carried `label_key`, a translation key, while the client read `label`.
 * Nothing in the pipeline resolves keys, so every authored label fell through to a hardcoded
 * "Open". The Jest test that "covered" it fed `{label: …}` — a shape the server had never sent.
 *
 * The second: `NotificationAction` has documented since spec 072 that a link renders only when the
 * actor passes the optional `ability`, and nothing enforced it. A viewer could be handed a link to
 * a screen that would refuse them on arrival.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Corex\Notifications\NotificationAction;

it('carries the label the author wrote, alongside the key', function () {
    $action = NotificationAction::to(
        'notifications.submission.new.action',
        'https://example.test/wp-admin/admin.php?page=corex-submissions',
        'corex_manage_submissions',
        'Open the Submission Inbox',
    );

    expect($action->toArray())->toBe([
        'label_key' => 'notifications.submission.new.action',
        'label'     => 'Open the Submission Inbox',
        'url'       => 'https://example.test/wp-admin/admin.php?page=corex-submissions',
        'ability'   => 'corex_manage_submissions',
    ]);
});

it('survives the round trip through storage without losing the label', function () {
    $action = NotificationAction::to('a.key', 'https://example.test/x', 'corex_manage_data', 'Download the export');

    $restored = NotificationAction::fromArray($action->toArray());

    expect($restored->label)->toBe('Download the export')
        ->and($restored->labelKey)->toBe('a.key')
        ->and($restored->url)->toBe('https://example.test/x')
        ->and($restored->ability)->toBe('corex_manage_data');
});

/**
 * Rows written before this change hold no `label` at all. They must rehydrate rather than fatal —
 * the client's own "Open" fallback is what they fall back to.
 */
it('rehydrates a row stored before labels existed', function () {
    $restored = NotificationAction::fromArray([
        'label_key' => 'notifications.readiness.resolve',
        'url'       => 'https://example.test/x',
        'ability'   => null,
    ]);

    expect($restored->label)->toBe('')
        ->and($restored->labelKey)->toBe('notifications.readiness.resolve')
        ->and($restored->ability)->toBeNull();
});

it('leaves the ability optional, because not every destination is gated', function () {
    $action = NotificationAction::to('a.key', 'https://example.test/x');

    expect($action->ability)->toBeNull()
        ->and($action->label)->toBe('');
});
