<?php

/**
 * Unit test for the pure notification mapper (Spec 068 US9, FR-158). Confirms a core
 * activity event projects to a safe front-office notification row.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Profile\Notification\NotificationList;

it('maps a core activity event to a safe notification row', function () {
    $event = new ActivityEvent(
        id: 1,
        eventUuid: '3f2504e0-4f89-41d3-9a0c-0305e82c3301',
        occurredAt: new DateTimeImmutable('2026-07-10T12:00:00+00:00'),
        actorId: 7,
        actorKind: 'user',
        actorLabel: 'Jane',
        area: 'security',
        kind: 'profile.updated',
        targetType: 'user',
        targetId: '7',
        targetLabel: 'Jane Doe',
        outcome: 'success',
        summary: ['key' => 'profile.updated', 'args' => []],
        context: [],
        sensitivity: 'personal',
        retentionUntil: new DateTimeImmutable('2027-07-10T12:00:00+00:00'),
    );

    $row = NotificationList::fromEvent($event);

    expect($row['area'])->toBe('security')
        ->and($row['kind'])->toBe('profile.updated')
        ->and($row['target'])->toBe('Jane Doe')
        ->and($row['outcome'])->toBe('success')
        ->and($row['summaryKey'])->toBe('profile.updated')
        ->and($row['occurredAt'])->toBe('2026-07-10T12:00:00+00:00');
});
