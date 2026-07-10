<?php

/**
 * Shared activity reconciliation across product domains (spec 068 T226).
 *
 * Every product domain records into one authoritative activity store. These tests write events
 * from several domains and prove the store reconciles them by area, actor, outcome, and time
 * window, and that expired events are pruned — so the Overview event feed and per-domain
 * activity views draw from the same reconciled source.
 *
 * @package Corex\Tests\Integration\Activity
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Config\Activity\ActivityTable;
use Corex\Config\Activity\WpActivityRepository;
use Corex\Database\Schema\Migrator;

const ACTIVITY_COVERAGE_UUIDS = [
    'data'        => 'a1111111-1111-4111-8111-111111111111',
    'submissions' => 'a2222222-2222-4222-8222-222222222222',
    'email'       => 'a3333333-3333-4333-8333-333333333333',
    'forms'       => 'a4444444-4444-4444-8444-444444444444',
    'access'      => 'a5555555-5555-4555-8555-555555555555',
    'expired'     => 'a6666666-6666-4666-8666-666666666666',
];

beforeEach(function () {
    global $wpdb;

    $this->migrator = new Migrator();
    $this->migrator->create((new ActivityTable())->schema());
    $this->service = new ActivityService(new WpActivityRepository($this->migrator));

    foreach (ACTIVITY_COVERAGE_UUIDS as $uuid) {
        $wpdb->delete($this->migrator->fullName(ActivityTable::NAME), ['event_uuid' => $uuid]);
    }
});

afterEach(function () {
    global $wpdb;

    foreach (ACTIVITY_COVERAGE_UUIDS as $uuid) {
        $wpdb->delete($this->migrator->fullName(ActivityTable::NAME), ['event_uuid' => $uuid]);
    }
});

function recordCoverageEvent(
    ActivityService $service,
    string $uuid,
    string $area,
    string $kind,
    int $actorId,
    string $outcome,
    string $occurredAt,
    string $retentionUntil,
): int {
    return $service->record(
        actorId: $actorId,
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: 'Coverage actor #' . $actorId,
        area: $area,
        kind: $kind,
        targetType: 'module',
        targetId: $area,
        targetLabel: ucfirst($area),
        outcome: $outcome,
        summary: ['key' => $kind, 'args' => ['area' => $area]],
        context: ['source' => 'coverage', 'area' => $area],
        sensitivity: ActivityEvent::SENSITIVITY_RESTRICTED,
        retentionUntil: new DateTimeImmutable($retentionUntil),
        occurredAt: new DateTimeImmutable($occurredAt),
        eventUuid: $uuid,
    )->id;
}

beforeEach(function () {
    $this->dataId = recordCoverageEvent($this->service, ACTIVITY_COVERAGE_UUIDS['data'], ActivityEvent::AREA_DATA, 'data.record.updated', 7, ActivityEvent::OUTCOME_SUCCESS, '2026-07-10T09:00:00+00:00', '2027-01-01T00:00:00+00:00');
    $this->submissionsId = recordCoverageEvent($this->service, ACTIVITY_COVERAGE_UUIDS['submissions'], ActivityEvent::AREA_SUBMISSIONS, 'submission.export.queued', 7, ActivityEvent::OUTCOME_QUEUED, '2026-07-10T09:05:00+00:00', '2027-01-01T00:00:00+00:00');
    $this->emailId = recordCoverageEvent($this->service, ACTIVITY_COVERAGE_UUIDS['email'], ActivityEvent::AREA_EMAIL, 'email.delivery.sent', 7, ActivityEvent::OUTCOME_SENT, '2026-07-10T09:10:00+00:00', '2027-01-01T00:00:00+00:00');
    $this->formsId = recordCoverageEvent($this->service, ACTIVITY_COVERAGE_UUIDS['forms'], ActivityEvent::AREA_FORMS, 'form.flow.published', 9, ActivityEvent::OUTCOME_SUCCESS, '2026-07-10T09:15:00+00:00', '2027-01-01T00:00:00+00:00');
    $this->accessId = recordCoverageEvent($this->service, ACTIVITY_COVERAGE_UUIDS['access'], ActivityEvent::AREA_ACCESS, 'access.request.denied', 9, ActivityEvent::OUTCOME_DENIED, '2026-07-10T09:20:00+00:00', '2027-01-01T00:00:00+00:00');
});

it('records events from every domain into one authoritative store', function () {
    foreach ([$this->dataId, $this->submissionsId, $this->emailId, $this->formsId, $this->accessId] as $id) {
        expect($id)->toBeGreaterThan(0);
    }

    $event = $this->service->find($this->dataId);
    expect($event)->not->toBeNull()
        ->and($event->area)->toBe(ActivityEvent::AREA_DATA)
        ->and($event->kind)->toBe('data.record.updated')
        ->and($event->context)->toBe(['source' => 'coverage', 'area' => 'data']);
});

it('reconciles activity by domain area', function () {
    $ids = fn (array $events): array => array_map(static fn (ActivityEvent $event): int => $event->id, $events);

    expect($ids($this->service->query(['area' => ActivityEvent::AREA_DATA], 1, 100)))->toContain($this->dataId)
        ->and($ids($this->service->query(['area' => ActivityEvent::AREA_DATA], 1, 100)))->not->toContain($this->submissionsId)
        ->and($ids($this->service->query(['area' => ActivityEvent::AREA_ACCESS], 1, 100)))->toContain($this->accessId)
        ->and($ids($this->service->query(['area' => ActivityEvent::AREA_ACCESS], 1, 100)))->not->toContain($this->emailId);
});

it('reconciles one actor cross-domain footprint and denied outcomes', function () {
    $ids = fn (array $events): array => array_map(static fn (ActivityEvent $event): int => $event->id, $events);

    $actorSeven = $ids($this->service->query(['actor_id' => 7], 1, 100));
    expect($actorSeven)->toContain($this->dataId, $this->submissionsId, $this->emailId)
        ->and($actorSeven)->not->toContain($this->formsId, $this->accessId);

    $denied = $ids($this->service->query(['outcome' => ActivityEvent::OUTCOME_DENIED], 1, 100));
    expect($denied)->toContain($this->accessId)
        ->and($denied)->not->toContain($this->dataId);
});

it('reconciles activity inside a time window', function () {
    $ids = fn (array $events): array => array_map(static fn (ActivityEvent $event): int => $event->id, $events);

    $window = $this->service->query([
        'date_from' => new DateTimeImmutable('2026-07-10T09:08:00+00:00'),
        'date_to' => new DateTimeImmutable('2026-07-10T09:17:00+00:00'),
    ], 1, 100);

    $windowIds = $ids($window);
    expect($windowIds)->toContain($this->emailId, $this->formsId)
        ->and($windowIds)->not->toContain($this->dataId, $this->accessId);
});

it('prunes only activity past its retention window', function () {
    $expiredId = recordCoverageEvent($this->service, ACTIVITY_COVERAGE_UUIDS['expired'], ActivityEvent::AREA_OPERATIONS, 'operation.expired', 7, ActivityEvent::OUTCOME_SUCCESS, '2026-01-01T00:00:00+00:00', '2026-02-01T00:00:00+00:00');

    $pruned = $this->service->pruneExpired(new DateTimeImmutable('2026-07-10T10:00:00+00:00'), 500);

    expect($pruned)->toBeGreaterThanOrEqual(1)
        ->and($this->service->find($expiredId))->toBeNull()
        // Live events with a future retention window survive.
        ->and($this->service->find($this->dataId))->not->toBeNull();
});
