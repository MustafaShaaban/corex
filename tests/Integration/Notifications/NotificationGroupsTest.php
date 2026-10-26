<?php

/**
 * Repeated notices of one kind are one item (spec 118, US-SHL-09), on real tables.
 *
 * Three finished exports of submissions and one of a data model read as "3 exports finished" and a
 * single item; the count a bell or the Overview shows counts the group once; marking every member
 * read ends the group and the next export starts a new one; two access requests stay two items; and
 * grouping writes nothing. The rows are this file's own (an actor no account has), and are removed.
 *
 * @package Corex\Tests\Integration\Notifications
 */

declare(strict_types=1);

use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Notifications\NotificationUserStateTable;
use Corex\Config\Notifications\WpNotificationRepository;
use Corex\Database\Schema\Migrator;
use Corex\Notifications\Notification;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationQuery;
use Corex\Notifications\NotificationRecipient;
use Corex\Notifications\NotificationSeverity;
use Corex\Tests\Support\NotificationRows;

const GROUPS_TEST_DEDUP = 'groups.test:';

beforeEach(function () {
    $migrator = new Migrator();
    $migrator->create((new NotificationTable())->schema());
    $migrator->create((new NotificationUserStateTable())->schema());
    $this->repo  = new WpNotificationRepository($migrator);
    $this->actor = 990_000_118;
    $this->can   = static fn (string $ability): bool => false;
    NotificationRows::forgetPrefixed(GROUPS_TEST_DEDUP);
});

afterEach(function () {
    NotificationRows::forgetPrefixed(GROUPS_TEST_DEDUP);
});

function groupsNotice(string $type, string $kind, string $key, string $at): Notification
{
    return Notification::create(
        type: $type,
        category: NotificationCategory::IMPORTS_EXPORTS,
        severity: NotificationSeverity::INFORMATION,
        sourceModule: 'jobs',
        titleKey: 't',
        messageKey: 'm',
        rendered: ['title' => 'Your export is ready to download', 'body' => 'B'],
        dedupKey: GROUPS_TEST_DEDUP . $key,
        recipient: NotificationRecipient::forUser(990_000_118),
        occurredAt: new DateTimeImmutable($at),
        metadata: $kind === '' ? [] : ['job_kind' => $kind],
    );
}

/** @return list<array<string,mixed>> */
function groupsList(object $test): array
{
    return $test->repo->queryForActor(NotificationQuery::fromRequest([], 1, 50), $test->actor, $test->can)['items'];
}

it('shows three finished exports of one kind as one item with a count, and another kind alone', function () {
    foreach ([1, 2, 3] as $n) {
        $this->repo->upsertByDedupKey(groupsNotice('export.ready', 'submissions.export', 'sub-' . $n, "2026-10-2{$n} 10:00:00"));
    }
    $this->repo->upsertByDedupKey(groupsNotice('export.ready', 'data.export', 'data-1', '2026-10-20 10:00:00'));

    $items = groupsList($this);

    expect($items)->toHaveCount(2)
        ->and($items[0]['rendered']['title'])->toBe('3 exports finished')
        ->and($items[0]['group']['count'])->toBe(3)
        ->and($items[0]['group']['members'])->toHaveCount(3)
        ->and($items[1])->not->toHaveKey('group')
        ->and($this->repo->unreadCountForActor($this->actor, $this->can))->toBe(2)
        // A way of showing: the four rows are still four rows.
        ->and(NotificationRows::countPrefixed(GROUPS_TEST_DEDUP))->toBe(4);
});

it('ends a group once its members are read, and the next export starts a new one', function () {
    foreach ([1, 2, 3] as $n) {
        $this->repo->upsertByDedupKey(groupsNotice('export.ready', 'submissions.export', 'sub-' . $n, "2026-10-2{$n} 10:00:00"));
    }
    foreach (groupsList($this)[0]['group']['members'] as $member) {
        $this->repo->markRead((int) $member['id'], $this->actor);
    }
    $this->repo->upsertByDedupKey(groupsNotice('export.ready', 'submissions.export', 'sub-4', '2026-10-24 10:00:00'));

    $items = groupsList($this);

    expect($items)->toHaveCount(4)
        ->and($items[0])->not->toHaveKey('group')
        ->and($this->repo->unreadCountForActor($this->actor, $this->can))->toBe(1);
});

it('keeps two access requests as two items', function () {
    $this->repo->upsertByDedupKey(groupsNotice('access.request', '', 'req-1', '2026-10-21 10:00:00'));
    $this->repo->upsertByDedupKey(groupsNotice('access.request', '', 'req-2', '2026-10-22 10:00:00'));

    expect(groupsList($this))->toHaveCount(2)
        ->and($this->repo->unreadCountForActor($this->actor, $this->can))->toBe(2);
});
