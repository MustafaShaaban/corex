<?php

/**
 * Activity as sentences (spec 118, US-OVR-07).
 *
 * Contract: the person by their display name today, "A deleted user" when gone; a kind with no
 * sentence reads "Something changed in <area>", never its key; a sentence links where its object
 * can be opened.
 *
 * @package Corex\Tests\Unit\Activity
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Activity\ActivityEvent;
use Corex\Config\Activity\ActivitySentences;
use Corex\Config\Operations\OperationsMode;
use Corex\Tests\Support\AdminUrlStubs;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('_n')->alias(static fn (string $single, string $plural, int $number): string => $number === 1 ? $single : $plural);
    Functions\when('number_format_i18n')->alias(static fn ($number): string => (string) $number);
    AdminUrlStubs::install();
    Functions\when('get_userdata')->alias(static function (int $id) {
        if ($id !== 7) {
            return false;
        }

        return (object) ['ID' => 7, 'display_name' => 'Mustafa'];
    });
});

function activityEvent(string $kind, array $overrides = []): ActivityEvent
{
    $now = new DateTimeImmutable('2026-10-23 10:00:00');

    return new ActivityEvent(...array_merge([
        'id' => 1,
        'eventUuid' => '00000000-0000-4000-8000-000000000001',
        'occurredAt' => $now,
        'actorId' => 7,
        'actorKind' => ActivityEvent::ACTOR_USER,
        'actorLabel' => 'User #7',
        'area' => ActivityEvent::AREA_SUBMISSIONS,
        'kind' => $kind,
        'targetType' => 'submission',
        'targetId' => '',
        'targetLabel' => '25 submissions',
        'outcome' => ActivityEvent::OUTCOME_SUCCESS,
        'summary' => ['key' => $kind, 'args' => []],
        'context' => ['count' => 25],
        'sensitivity' => ActivityEvent::SENSITIVITY_RESTRICTED,
        'retentionUntil' => $now->modify('+1 year'),
    ], $overrides));
}

function sentences(): ActivitySentences
{
    return new ActivitySentences(new OperationsMode());
}

it('says who moved how many submissions to the trash, and links to the trash', function () {
    $said = sentences()->of(activityEvent('submission.trashed'));

    expect($said['text'])->toBe('Mustafa moved 25 submissions to the trash')
        ->and($said['href'])->toBe(AdminUrlStubs::BASE . 'admin.php?page=corex-submissions&view=trash');
});

it('calls a person who no longer exists a deleted user', function () {
    expect(sentences()->of(activityEvent('submission.trashed', ['actorId' => 99]))['text'])
        ->toBe('A deleted user moved 25 submissions to the trash');
});

it('says something changed in the area for a kind with no sentence, never its key', function () {
    $said = sentences()->of(activityEvent('submission.something_new', ['area' => ActivityEvent::AREA_EMAIL]));

    expect($said['text'])->toBe('Something changed in Email Studio')
        ->and($said['text'])->not->toContain('.');
});

it('says a refusal the same way for the activity log and the audit log', function () {
    $event = activityEvent('access.denied', ['area' => ActivityEvent::AREA_ACCESS, 'targetId' => 'corex-data', 'context' => ['refusal' => 'denied']]);

    expect(sentences()->of($event)['text'])->toBe(sentences()->refused('Mustafa', 'corex-data'))
        ->and(sentences()->refused('Mustafa', 'corex-data'))->toBe('Mustafa was refused access to Data');
});

/** A mode change to Production, recorded with what it cleared (spec 124, FR-004b), or before that was recorded. */
function modeChangedEvent(?array $pageCaches): ActivityEvent
{
    $context = ['from' => OperationsMode::DEVELOPMENT, 'to' => OperationsMode::PRODUCTION];
    if ($pageCaches !== null) {
        $context['page_caches'] = $pageCaches;
    }

    return activityEvent('operations.mode.changed', [
        'area' => ActivityEvent::AREA_OPERATIONS,
        'targetType' => 'mode',
        'targetId' => OperationsMode::PRODUCTION,
        'context' => $context,
    ]);
}

it('FR-004b: says which page-cache plugins a mode change asked to clear, by their product names', function () {
    Functions\when('wp_sprintf_l')->alias(static fn (string $pattern, array $items): string => implode(' and ', $items));

    expect(sentences()->of(modeChangedEvent(['asked' => ['wp-rocket', 'litespeed-cache'], 'failed' => []]))['text'])
        ->toBe('Mustafa changed the mode to Production and asked WP Rocket and LiteSpeed Cache to clear their page caches')
        ->and(sentences()->of(modeChangedEvent(['asked' => ['wp-rocket'], 'failed' => []]))['text'])
        ->toBe('Mustafa changed the mode to Production and asked WP Rocket to clear its page cache');
});

it('FR-004b: says so when a mode change found no page-cache plugin it could ask', function () {
    expect(sentences()->of(modeChangedEvent(['asked' => [], 'failed' => []]))['text'])
        ->toBe('Mustafa changed the mode to Production. No page-cache plugin CoreX can ask was active');
});

it('FR-004b: says which page cache could not be cleared, beside the plugins that were asked', function () {
    Functions\when('wp_sprintf_l')->alias(static fn (string $pattern, array $items): string => implode(' and ', $items));

    expect(sentences()->of(modeChangedEvent(['asked' => ['wp-rocket'], 'failed' => ['w3-total-cache']]))['text'])
        ->toBe('Mustafa changed the mode to Production and asked WP Rocket to clear its page cache. The page cache of W3 Total Cache could not be cleared')
        ->and(sentences()->of(modeChangedEvent(['asked' => [], 'failed' => ['w3-total-cache']]))['text'])
        ->toBe('Mustafa changed the mode to Production. The page cache of W3 Total Cache could not be cleared');
});

it('FR-004b: says a mode change recorded before page caches were cleared as it was said then', function () {
    expect(sentences()->of(modeChangedEvent(null))['text'])->toBe('Mustafa changed the mode to Production');
});
