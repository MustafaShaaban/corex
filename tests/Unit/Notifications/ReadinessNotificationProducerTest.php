<?php

/**
 * Unit tests for the readiness notification producer (spec 072 US4: FR-013, FR-010).
 *
 * Readiness is a condition, not an event: a check that becomes blocking raises a notification keyed by
 * the check, and a raised check that passes resolves it. Only a change of state is acted on, so the
 * same blocker evaluated again by every screen is not published again (spec 118, US-OVR-01).
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

    // Which checks are raised is remembered between evaluations (spec 118).
    $GLOBALS['corex_test_options'] = [];
    Functions\when('get_option')->alias(
        static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default,
    );
    Functions\when('update_option')->alias(static function (string $key, $value): bool {
        $GLOBALS['corex_test_options'][$key] = $value;

        return true;
    });
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

it('publishes a blocker once however many times readiness is evaluated', function () {
    $service = new RecordingNotificationService();

    for ($i = 0; $i < 10; $i++) {
        reconcileReadiness($service, readinessSnapshot(['debug_display' => 'blocking']));
    }

    expect($service->published)->toHaveCount(1);
});

it('resolves a raised check that passes and raises it again only when it fails again', function () {
    $service = new RecordingNotificationService();

    reconcileReadiness($service, readinessSnapshot(['debug_display' => 'blocking']));
    reconcileReadiness($service, readinessSnapshot(['debug_display' => 'pass']));
    reconcileReadiness($service, readinessSnapshot(['debug_display' => 'pass']));
    reconcileReadiness($service, readinessSnapshot(['debug_display' => 'blocking']));

    expect($service->published)->toHaveCount(2)
        ->and($service->resolved)->toBe(['readiness.blocker:debug_display']);
});

it('leaves a raised check as it is while it cannot be read', function () {
    $service = new RecordingNotificationService();

    reconcileReadiness($service, readinessSnapshot(['ssl' => 'blocking']));
    reconcileReadiness($service, readinessSnapshot(['ssl' => 'unavailable']));
    reconcileReadiness($service, readinessSnapshot(['ssl' => 'blocking']));

    expect($service->published)->toHaveCount(1)
        ->and($service->resolved)->toBe([]);
});

it('rewords a raised blocker in the words of the request that evaluates it, without another occurrence', function () {
    $service = new RecordingNotificationService();

    reconcileReadiness($service, readinessSnapshot(['ssl' => 'blocking']));
    reconcileReadiness($service, readinessSnapshot(['ssl' => 'blocking']));

    expect($service->published)->toHaveCount(1)
        ->and($service->reworded)->toHaveCount(1)
        ->and($service->reworded[0]->dedupKey)->toBe('readiness.blocker:ssl');
});
