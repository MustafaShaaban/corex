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
use Corex\Config\Security\LoginProtection\LoginAccounts;
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

/** Accounts named the way the lockout notice names them, from `get_userdata()` as stubbed. */
final class SentencesTestAccounts implements LoginAccounts
{
    public function idFor(string $typed): ?int
    {
        return null;
    }

    public function nameOf(int $userId): string
    {
        $user = get_userdata($userId);
        if (! is_object($user)) {
            return 'User ' . $userId;
        }

        return $user->display_name === $user->user_login ? $user->user_login : $user->display_name . ' (' . $user->user_login . ')';
    }
}

function sentences(): ActivitySentences
{
    return new ActivitySentences(new OperationsMode(), new SentencesTestAccounts());
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

/** A lockout released from the screen (spec 124, FR-017), of an account or of an unknown username. */
function lockoutReleasedEvent(array $context): ActivityEvent
{
    return activityEvent('security.login.released', [
        'area'       => ActivityEvent::AREA_SECURITY,
        'targetType' => 'login',
        'targetId'   => 'sign-in',
        'context'    => $context,
    ]);
}

it('FR-017, User Story 8 scenario 2: says who released the lockout of which account, and links to the login tab', function () {
    Functions\when('get_userdata')->alias(static fn (int $id) => match ($id) {
        7  => (object) ['ID' => 7, 'display_name' => 'Mustafa', 'user_login' => 'mustafa'],
        12 => (object) ['ID' => 12, 'display_name' => 'Sara Adel', 'user_login' => 'sara'],
        13 => (object) ['ID' => 13, 'display_name' => 'sara2', 'user_login' => 'sara2'],
        default => false,
    });

    $said = sentences()->of(lockoutReleasedEvent(['count' => 1, 'account_id' => 12, 'identity' => 'aaaaaaaaaaaa', 'network' => 'bbbbbbbbbbbb']));

    expect($said['text'])->toBe('Mustafa released the sign-in lockout of Sara Adel (sara)')
        ->and($said['href'])->toBe(AdminUrlStubs::BASE . 'admin.php?page=corex-operations-security&tab=login')
        // The login alone when it is the display name, as the lockout notice names it (DECISIONS #626).
        ->and(sentences()->of(lockoutReleasedEvent(['count' => 1, 'account_id' => 13]))['text'])
        ->toBe('Mustafa released the sign-in lockout of sara2')
        // A deleted account reads as the lockout notice reads it.
        ->and(sentences()->of(lockoutReleasedEvent(['count' => 1, 'account_id' => 99]))['text'])
        ->toBe('Mustafa released the sign-in lockout of User 99');
});

it('FR-016, FR-017, User Story 8 scenario 3: says an unknown username by its fingerprint', function () {
    expect(sentences()->of(lockoutReleasedEvent(['count' => 1, 'account_id' => 0, 'identity' => '0123456789ab', 'network' => 'ba9876543210']))['text'])
        ->toBe('Mustafa released the sign-in lockout of an unknown username (fingerprint 0123456789ab)');
});

it('FR-017, User Story 8 scenario 4: says who released every active lockout, and how many', function () {
    $all = static fn (int $count): ActivityEvent => activityEvent('security.login.released_all', [
        'area'    => ActivityEvent::AREA_SECURITY,
        'context' => ['count' => $count],
    ]);

    expect(sentences()->of($all(3))['text'])->toBe('Mustafa released all 3 active sign-in lockouts')
        ->and(sentences()->of($all(1))['text'])->toBe('Mustafa released the 1 active sign-in lockout')
        ->and(sentences()->of($all(3))['href'])->toBe(AdminUrlStubs::BASE . 'admin.php?page=corex-operations-security&tab=login');
});
