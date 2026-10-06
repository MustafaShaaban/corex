<?php

/**
 * Unit tests for the readiness notification producer (spec 072 US4: FR-013, FR-010).
 *
 * Readiness is a condition, not an event: a blocking check raises a notification keyed by the check,
 * and a check that now passes resolves it. Reconciliation is idempotent across evaluations.
 *
 * @package Corex\Tests\Unit\Notifications
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Support\AdminUrlStubs;
use Corex\Access\CorexAbility;
use Corex\Config\Notifications\Producers\ReadinessNotificationProducer;
use Corex\Config\Operations\ReadinessEvaluatedEvent;
use Corex\Config\Operations\ReadinessSnapshot;
use Corex\Events\ListenerProvider;
use Corex\Notifications\NotificationCategory;
use Corex\Notifications\NotificationService;
use Corex\Tests\Support\RecordingNotificationService;

beforeEach(function () {
    Functions\stubTranslationFunctions();
    // Producers name a destination now, and building it calls WordPress (spec 087, FR-014).
    AdminUrlStubs::install();
});

/** @param array<string,string> $states key => state */
function readinessSnapshot(array $states): ReadinessSnapshot
{
    $checks = [];
    foreach ($states as $key => $state) {
        $checks[] = [
            'key'            => $key,
            'label'          => ucfirst($key),
            'state'          => $state,
            'summary'        => 'Check ' . $key,
            'resolution_url' => '',
            'checked_at'     => '2026-07-21T10:00:00+00:00',
            'evidence_hash'  => hash('sha256', $key),
        ];
    }

    return new ReadinessSnapshot($checks);
}

function reconcileReadiness(NotificationService $service, ReadinessSnapshot $snapshot): NotificationService
{
    $listeners = new ListenerProvider();
    (new ReadinessNotificationProducer($service, $listeners))->register();
    $event = new ReadinessEvaluatedEvent($snapshot);
    foreach ($listeners->listenersFor($event) as $listener) {
        $listener($event);
    }

    return $service;
}

it('raises a notification for a blocking readiness check', function () {
    $service = reconcileReadiness(new RecordingNotificationService(), readinessSnapshot(['https' => 'blocking']));

    expect($service->published)->toHaveCount(1);
    $note = $service->published[0];
    expect($note->type)->toBe('readiness.blocker')
        ->and($note->category)->toBe(NotificationCategory::READINESS)
        ->and($note->dedupKey)->toBe('readiness.blocker:https')
        ->and($note->recipient->canBeSeenBy(9, fn (string $a): bool => $a === CorexAbility::MANAGE_OPERATIONS))->toBeTrue();
});

/**
 * A readiness check's label states the condition that passes: "File editing disabled", "No default
 * "admin" account". The title used to be "<label> is not ready for production", which read as the
 * opposite of the fault for five of the six checks — reported on 2026-10-06 as "No default "admin"
 * account is not ready for production".
 */
it('titles a blocker as a condition that is not met', function () {
    $snapshot = new ReadinessSnapshot([[
        'key'            => 'default_admin',
        'label'          => 'No default "admin" account',
        'state'          => 'blocking',
        'summary'        => 'Avoid a user named "admin".',
        'resolution_url' => '',
        'checked_at'     => '2026-10-06T10:00:00+00:00',
        'evidence_hash'  => hash('sha256', 'default_admin'),
    ]]);

    $service = reconcileReadiness(new RecordingNotificationService(), $snapshot);

    expect($service->published[0]->rendered['title'])
        ->toBe('Readiness check not met: No default "admin" account');
});

it('resolves a check that now passes and never publishes for it', function () {
    $service = reconcileReadiness(new RecordingNotificationService(), readinessSnapshot(['https' => 'pass']));

    expect($service->published)->toBeEmpty()
        ->and($service->resolved)->toBe(['readiness.blocker:https']);
});

it('publishes blockers and resolves the rest in one reconciliation', function () {
    $service = reconcileReadiness(
        new RecordingNotificationService(),
        readinessSnapshot(['https' => 'blocking', 'cron' => 'pass', 'debug' => 'blocking']),
    );

    $keys = array_map(fn ($n): string => $n->dedupKey, $service->published);
    expect($keys)->toBe(['readiness.blocker:https', 'readiness.blocker:debug'])
        ->and($service->resolved)->toBe(['readiness.blocker:cron']);
});

it('is keyed and available like the other producers', function () {
    $producer = new ReadinessNotificationProducer(new RecordingNotificationService(), new ListenerProvider());

    expect($producer->key())->toBe('operations.readiness')
        ->and($producer->isAvailable())->toBe(class_exists(ReadinessEvaluatedEvent::class));
});
