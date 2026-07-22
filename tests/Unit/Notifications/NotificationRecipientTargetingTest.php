<?php

/**
 * Unit tests for "is this notification aimed at me personally?" (spec 072 FR-018, the
 * "assigned to me" view).
 *
 * Distinct from `canBeSeenBy`, which answers a broader question — an ability-targeted notification
 * is visible to everyone holding that ability, but it is nobody's *personally*. The saved view needs
 * the narrower question, or "Assigned to me" would show every notification a manager can see.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Corex\Notifications\NotificationRecipient;

it('treats a single-user target as aimed at that user', function () {
    $recipient = NotificationRecipient::forUser(7);

    expect($recipient->targetsUserDirectly(7))->toBeTrue()
        ->and($recipient->targetsUserDirectly(8))->toBeFalse();
});

it('treats a multi-user target as aimed at each named user', function () {
    $recipient = NotificationRecipient::forUsers([7, 9]);

    expect($recipient->targetsUserDirectly(7))->toBeTrue()
        ->and($recipient->targetsUserDirectly(9))->toBeTrue()
        ->and($recipient->targetsUserDirectly(8))->toBeFalse();
});

it('treats the assignee of a source object as aimed at them', function () {
    $recipient = NotificationRecipient::forAssigned('submission', '42', 7, 'corex_manage_submissions');

    expect($recipient->targetsUserDirectly(7))->toBeTrue();
});

it('does not treat an ability-wide notification as aimed at any one person', function () {
    // The manager can see it, and should — but it is not "assigned to me". Without this the view
    // would just repeat the inbox for anyone holding the ability.
    expect(NotificationRecipient::forAbility('corex_manage_submissions')->targetsUserDirectly(7))
        ->toBeFalse()
        ->and(NotificationRecipient::forCategoryAdmins('corex_manage_operations')->targetsUserDirectly(7))
        ->toBeFalse();
});

it('is not satisfied by the manager ability on an assigned notification', function () {
    // forAssigned() carries both an assignee and a manager ability; only the assignee is targeted
    // personally, even though canBeSeenBy() admits both.
    $recipient = NotificationRecipient::forAssigned('submission', '42', 7, 'corex_manage_submissions');

    expect($recipient->targetsUserDirectly(8))->toBeFalse()
        ->and($recipient->canBeSeenBy(8, static fn (string $a): bool => true))->toBeTrue();
});
