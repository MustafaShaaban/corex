<?php

/**
 * Unit tests for activity persistence orchestration (spec 068: FR-005, FR-018, FR-149).
 *
 * @package Corex\Tests\Unit\Activity
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityRepository;
use Corex\Activity\ActivityService;

function activityRepository(): ActivityRepository
{
    return new class implements ActivityRepository {
        /** @var list<ActivityEvent> */
        public array $events = [];

        public function append(ActivityEvent $event): ActivityEvent
        {
            $stored         = $event->withId(count($this->events) + 1);
            $this->events[] = $stored;

            return $stored;
        }

        public function find(int $id): ?ActivityEvent
        {
            return $this->events[$id - 1] ?? null;
        }

        public function query(array $filters = [], int $page = 1, int $perPage = 20): array
        {
            $events = array_values(array_filter(
                $this->events,
                static fn (ActivityEvent $event): bool => ! isset($filters['area']) || $event->area === $filters['area'],
            ));

            return array_slice($events, ($page - 1) * $perPage, $perPage);
        }

        public function pruneExpired(DateTimeImmutable $now, int $limit = 500): int
        {
            $expired      = array_filter(
                $this->events,
                static fn (ActivityEvent $event): bool => $event->retentionUntil <= $now,
            );
            $expiredCount = min(count($expired), $limit);
            $expiredIds   = array_slice(array_keys($expired), 0, $expiredCount);

            $this->events = array_values(array_filter(
                $this->events,
                static fn (ActivityEvent $event, int $key): bool => ! in_array($key, $expiredIds, true),
                ARRAY_FILTER_USE_BOTH,
            ));

            return $expiredCount;
        }
    };
}

it('records an authoritative outcome through the repository', function () {
    $repository = activityRepository();
    $service    = new ActivityService($repository);

    $stored = $service->record(
        actorId: 9,
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: 'Site owner',
        area: ActivityEvent::AREA_ACCESS,
        kind: 'access.granted',
        targetType: 'user',
        targetId: '15',
        targetLabel: 'Editor',
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => 'access.granted', 'args' => ['ability' => 'corex_manage_forms']],
        context: ['ability' => 'corex_manage_forms'],
        sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
        retentionUntil: new DateTimeImmutable('+90 days'),
        occurredAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        eventUuid: 'f6773ddc-2d63-40cc-b408-35c0a81c084b',
    );

    expect($stored->id)->toBe(1)
        ->and($repository->find(1))->toBe($stored)
        ->and($stored->context)->toBe(['ability' => 'corex_manage_forms']);
});
it('queries through the repository without exposing persistence details', function () {
    $repository = activityRepository();
    $service    = new ActivityService($repository);

    foreach ([ActivityEvent::AREA_FORMS, ActivityEvent::AREA_EMAIL] as $area) {
        $service->record(
            actorId: 0,
            actorKind: ActivityEvent::ACTOR_SYSTEM,
            actorLabel: 'CoreX',
            area: $area,
            kind: 'module.checked',
            targetType: 'module',
            targetId: $area,
            targetLabel: ucfirst($area),
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => 'module.checked', 'args' => ['area' => $area]],
            context: [],
            sensitivity: ActivityEvent::SENSITIVITY_PUBLIC_ADMIN,
            retentionUntil: new DateTimeImmutable('+30 days'),
        );
    }

    expect($service->query(['area' => ActivityEvent::AREA_EMAIL]))->toHaveCount(1)
        ->and($service->query(['area' => ActivityEvent::AREA_EMAIL])[0]->area)->toBe(ActivityEvent::AREA_EMAIL);
});

it('delegates bounded retention pruning', function () {
    $repository = activityRepository();
    $service    = new ActivityService($repository);

    foreach (['-2 days', '-1 day', '+1 day'] as $retention) {
        $service->record(
            actorId: 0,
            actorKind: ActivityEvent::ACTOR_CRON,
            actorLabel: 'CoreX scheduler',
            area: ActivityEvent::AREA_OPERATIONS,
            kind: 'retention.checked',
            targetType: 'activity_log',
            targetId: '',
            targetLabel: 'Activity log',
            outcome: ActivityEvent::OUTCOME_SUCCESS,
            summary: ['key' => 'retention.checked', 'args' => []],
            context: [],
            sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
            retentionUntil: new DateTimeImmutable($retention),
        );
    }

    expect($service->pruneExpired(new DateTimeImmutable(), 1))->toBe(1)
        ->and($repository->events)->toHaveCount(2);
});

/**
 * The record as it is kept when a caller hands over these three texts, the only ones of a
 * record that a person can have chosen: a display name, a version a package says it is.
 */
function recordedWith(string $actorLabel, string $targetId, string $targetLabel): ActivityEvent
{
    return (new ActivityService(activityRepository()))->record(
        actorId: 9,
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: $actorLabel,
        area: ActivityEvent::AREA_SECURITY,
        kind: 'profile.updated',
        targetType: 'user',
        targetId: $targetId,
        targetLabel: $targetLabel,
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => 'profile.updated', 'args' => []],
        context: [],
        sensitivity: ActivityEvent::SENSITIVITY_PERSONAL,
        retentionUntil: new DateTimeImmutable('+1 year'),
    );
}

it('keeps a label and a target id of exactly the length the record holds', function () {
    $longest = str_repeat('n', ActivityEvent::LONGEST_TEXT);

    $event = recordedWith($longest, $longest, $longest);

    expect($event->actorLabel)->toBe($longest)
        ->and($event->targetId)->toBe($longest)
        ->and($event->targetLabel)->toBe($longest);
});

it('cuts a longer label or target id to that length and ends it with an ellipsis', function () {
    $tooLong = str_repeat('n', ActivityEvent::LONGEST_TEXT + 1);
    $cut     = str_repeat('n', ActivityEvent::LONGEST_TEXT - 1) . '…';

    $event = recordedWith($tooLong, $tooLong, $tooLong);

    expect($event->actorLabel)->toBe($cut)
        ->and($event->targetId)->toBe($cut)
        ->and($event->targetLabel)->toBe($cut);
});

it('counts the length of a label in characters, as the table does', function () {
    // An Arabic letter is two bytes: 191 of them fit, and are 382 bytes.
    $arabic = str_repeat('م', ActivityEvent::LONGEST_TEXT);

    $event = recordedWith($arabic, '9', $arabic . 'م');

    expect($event->actorLabel)->toBe($arabic)
        ->and(mb_strlen($event->targetLabel))->toBe(ActivityEvent::LONGEST_TEXT)
        ->and($event->targetLabel)->toBe(mb_substr($arabic, 0, ActivityEvent::LONGEST_TEXT - 1) . '…');
});
