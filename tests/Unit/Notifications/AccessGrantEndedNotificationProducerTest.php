<?php

/**
 * Who is told when a grant made by approval ends (spec 112, FR-006).
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Access\AccessGrantEndedEvent;
use Corex\Access\AccessRequestStore;
use Corex\Access\AccessUserDirectory;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\AccessGrantEndedNotificationProducer;
use Corex\Events\ListenerProvider;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
});

/**
 * @param list<int> $existing The accounts that still exist.
 * @return list<int>          Who was told, by user id.
 */
function toldWhenGrantEnds(string $reason, array $existing): array
{
    $users = Mockery::mock(AccessUserDirectory::class);
    $users->allows('exists')->andReturnUsing(static fn (int $id): bool => in_array($id, $existing, true));
    $users->allows('displayName')->andReturn('Sara Ahmed');

    $service   = new RecordingNotificationService();
    $listeners = new ListenerProvider();
    (new AccessGrantEndedNotificationProducer($service, $listeners, $users))->register();

    $event = new AccessGrantEndedEvent(41, 12, 7, [CorexAbility::MANAGE_FORMS], $reason);
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return array_merge(...array_map(
        static fn ($note): array => $note->recipient->toArray()['user_ids'],
        $service->published,
    ));
}

it('tells the person and the approver when an expiry ends a grant', function () {
    expect(toldWhenGrantEnds(AccessRequestStore::ENDED_EXPIRED, [12, 7]))->toBe([12, 7]);
});

it('still tells the person when the approver account is gone', function () {
    expect(toldWhenGrantEnds(AccessRequestStore::ENDED_EXPIRED, [12]))->toBe([12]);
});

it('tells nobody when an administrator revoked the grant', function () {
    expect(toldWhenGrantEnds(AccessRequestStore::ENDED_REVOKED, [12, 7]))->toBe([]);
});
