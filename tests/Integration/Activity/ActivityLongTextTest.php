<?php

/**
 * A record whose labels are longer than its table holds is written, and is not refused.
 *
 * The table holds an actor's label, a target's label and a target's id in 191 characters. A
 * caller hands over a person's display name, which WordPress keeps in 250, or the version an
 * uploaded package says it is, which has no length: `$wpdb->insert()` refuses a value longer
 * than its column, and the repository raised. Measured over HTTP on 2026-10-13: a saved
 * profile answered 500, and so did an access request, which was stored once for each try
 * (DECISIONS #340).
 *
 * On the real table, through the service every caller records with.
 *
 * @package Corex\Tests\Integration\Activity
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Activity\ActivityTable;
use Corex\Database\Schema\Migrator;

const ACTIVITY_LONG_TEXT_UUID = '5a1f0b1e-6c0d-4b53-9d0a-2f6a1c7e9b44';

function forgetLongTextRecord(): void
{
    global $wpdb;

    $wpdb->delete(
        Boot::app()->container()->make(Migrator::class)->fullName(ActivityTable::NAME),
        ['event_uuid' => ACTIVITY_LONG_TEXT_UUID],
    );
}

beforeEach(fn () => forgetLongTextRecord());
afterEach(fn () => forgetLongTextRecord());

it('stores a record whose labels and target id are longer than its columns', function (string $text) {
    $activity = Boot::app()->container()->make(ActivityService::class);

    $recorded = $activity->record(
        actorId: 0,
        actorKind: ActivityEvent::ACTOR_SYSTEM,
        actorLabel: $text,
        area: ActivityEvent::AREA_OPERATIONS,
        kind: 'integration.long_text',
        targetType: 'release',
        targetId: $text,
        targetLabel: $text,
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => 'integration.long_text', 'args' => []],
        context: [],
        sensitivity: ActivityEvent::SENSITIVITY_RESTRICTED,
        retentionUntil: new DateTimeImmutable('+1 day'),
        eventUuid: ACTIVITY_LONG_TEXT_UUID,
    );
    $stored = $activity->find($recorded->id);
    $cut    = mb_substr($text, 0, ActivityEvent::LONGEST_TEXT - 1) . '…';

    expect($stored)->not->toBeNull()
        ->and($stored->actorLabel)->toBe($cut)
        ->and($stored->targetId)->toBe($cut)
        ->and($stored->targetLabel)->toBe($cut);
})->with([
    'a display name of the longest WordPress keeps' => [str_repeat('n', 250)],
    'the same in Arabic' => [str_repeat('م', 250)],
    'a version a package made up' => ['1.2.3-' . str_repeat('x', 400)],
]);
