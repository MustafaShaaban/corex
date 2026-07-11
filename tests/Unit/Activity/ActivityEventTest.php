<?php

/**
 * Unit tests for the shared append-only activity event (spec 068: FR-005, FR-018).
 *
 * @package Corex\Tests\Unit\Activity
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;

it('captures a complete immutable activity event', function () {
    $occurredAt = new DateTimeImmutable('2026-07-03T09:30:00+00:00');
    $retainedTo = new DateTimeImmutable('2026-10-01T00:00:00+00:00');

    $event = new ActivityEvent(
        id: 41,
        eventUuid: '2f09598f-f453-47a1-bf0e-9fcf488cdf1d',
        occurredAt: $occurredAt,
        actorId: 7,
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: 'Site owner',
        area: ActivityEvent::AREA_SETTINGS,
        kind: 'settings.updated',
        targetType: 'settings_section',
        targetId: 'general',
        targetLabel: 'General settings',
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => 'settings.updated', 'args' => ['section' => 'General']],
        context: ['changed_keys' => ['site_name', 'timezone']],
        sensitivity: ActivityEvent::SENSITIVITY_RESTRICTED,
        retentionUntil: $retainedTo,
    );

    expect($event->id)->toBe(41)
        ->and($event->occurredAt)->toBe($occurredAt)
        ->and($event->summary['key'])->toBe('settings.updated')
        ->and($event->context['changed_keys'])->toBe(['site_name', 'timezone'])
        ->and($event->retentionUntil)->toBe($retainedTo)
        ->and(fn () => $event->outcome = ActivityEvent::OUTCOME_FAILURE)->toThrow(Error::class);
});

it('serializes to the stable persistence contract', function () {
    $event = new ActivityEvent(
        id: 0,
        eventUuid: '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        occurredAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        actorId: 0,
        actorKind: ActivityEvent::ACTOR_CRON,
        actorLabel: 'CoreX scheduler',
        area: ActivityEvent::AREA_OPERATIONS,
        kind: 'retention.pruned',
        targetType: 'activity_log',
        targetId: '',
        targetLabel: 'Activity log',
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => 'retention.pruned', 'args' => ['count' => 12]],
        context: ['count' => 12],
        sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
        retentionUntil: new DateTimeImmutable('2026-08-02T10:00:00+00:00'),
    );

    expect($event->toArray())->toMatchArray([
        'id'          => 0,
        'event_uuid'  => '64d15a02-8cf2-4e47-9ea3-fbbbc22ce22c',
        'actor_kind'  => 'cron',
        'area'        => 'operations',
        'kind'        => 'retention.pruned',
        'outcome'     => 'success',
        'sensitivity' => 'security',
    ]);
});

it('rejects unsupported classifications and malformed identifiers', function (string $field, mixed $value) {
    $arguments = [
        'id'             => 0,
        'eventUuid'      => '7bb7b752-5ae8-47b8-b09a-b5cc26c3bfd9',
        'occurredAt'     => new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        'actorId'        => 0,
        'actorKind'      => ActivityEvent::ACTOR_SYSTEM,
        'actorLabel'     => 'CoreX',
        'area'           => ActivityEvent::AREA_OVERVIEW,
        'kind'           => 'corex.checked',
        'targetType'     => 'site',
        'targetId'       => '1',
        'targetLabel'    => 'Site',
        'outcome'        => ActivityEvent::OUTCOME_SUCCESS,
        'summary'        => ['key' => 'corex.checked', 'args' => []],
        'context'        => [],
        'sensitivity'    => ActivityEvent::SENSITIVITY_PUBLIC_ADMIN,
        'retentionUntil' => new DateTimeImmutable('2026-08-02T10:00:00+00:00'),
    ];
    $arguments[$field] = $value;

    expect(fn () => new ActivityEvent(...$arguments))->toThrow(InvalidArgumentException::class);
})->with([
    'negative id'      => ['id', -1],
    'invalid uuid'     => ['eventUuid', 'not-a-uuid'],
    'invalid actor'    => ['actorKind', 'browser'],
    'invalid area'     => ['area', 'unknown'],
    'invalid kind'     => ['kind', 'Not Valid'],
    'invalid outcome'  => ['outcome', 'maybe'],
    'invalid exposure' => ['sensitivity', 'secret'],
]);

it('rejects secret-bearing context keys recursively', function () {
    expect(fn () => new ActivityEvent(
        id: 0,
        eventUuid: '13425f8a-92ed-4b08-b04a-7ba6254cefc0',
        occurredAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        actorId: 3,
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: 'Administrator',
        area: ActivityEvent::AREA_EMAIL,
        kind: 'email.settings.updated',
        targetType: 'mail_provider',
        targetId: 'smtp',
        targetLabel: 'SMTP provider',
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => 'email.settings.updated', 'args' => []],
        context: ['provider' => ['api_token' => 'must-not-be-logged']],
        sensitivity: ActivityEvent::SENSITIVITY_RESTRICTED,
        retentionUntil: new DateTimeImmutable('2026-08-02T10:00:00+00:00'),
    ))->toThrow(InvalidArgumentException::class, 'secret-bearing');
});

it('rejects secret-bearing translation arguments', function () {
    expect(fn () => new ActivityEvent(
        id: 0,
        eventUuid: 'ad55ac2e-613e-4da4-bbb8-c4ef1cadbf9b',
        occurredAt: new DateTimeImmutable('2026-07-03T10:00:00+00:00'),
        actorId: 3,
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: 'Administrator',
        area: ActivityEvent::AREA_SETTINGS,
        kind: 'settings.updated',
        targetType: 'settings',
        targetId: 'mail',
        targetLabel: 'Mail settings',
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => 'settings.updated', 'args' => ['api_key' => 'must-not-be-logged']],
        context: [],
        sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
        retentionUntil: new DateTimeImmutable('2026-08-02T10:00:00+00:00'),
    ))->toThrow(InvalidArgumentException::class, 'secret-bearing');
});
