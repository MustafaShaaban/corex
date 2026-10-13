<?php

/**
 * Unit test for the pure notification mapper (Spec 068 US9, FR-158). Confirms a core
 * activity event projects to a safe front-office notification row, with the sentence a
 * person reads (FR-161g). What the row says of the date is held on real WordPress, in
 * `tests/Integration/Profile/AccountListRowsTest.php`: the site's formatter writes it.
 *
 * @package Corex\Tests\Unit\Profile
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Activity\ActivityEvent;
use Corex\Profile\Notification\NotificationList;
use Corex\Profile\Support\SiteDate;

it('maps a core activity event to a safe notification row', function () {
    Functions\when('__')->returnArg();
    Functions\when('get_option')->justReturn('Y-m-d');
    Functions\when('wp_date')->justReturn('2026-07-10');

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

    $row = NotificationList::fromEvent($event, new SiteDate());

    expect($row['label'])->toBe('Your profile was updated.')
        ->and($row['area'])->toBe('security')
        ->and($row['kind'])->toBe('profile.updated')
        ->and($row['target'])->toBe('Jane Doe')
        ->and($row['outcome'])->toBe('success')
        ->and($row['summaryKey'])->toBe('profile.updated')
        ->and($row['occurredAt'])->toBe('2026-07-10T12:00:00+00:00');
});
