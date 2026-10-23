<?php

/**
 * One access history (spec 117, US-ACC-04): the activity stream's access events and the counted
 * refusals, newest first, in words, filtered by kind and by person, 25 a page.
 *
 * The events and refusals are this file's own, and the person filter keeps the assertions to them
 * on an install whose history holds other people's.
 *
 * @package Corex\Tests\Integration\Access
 */

declare(strict_types=1);

use Corex\Access\CorexAbility;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Access\AccessHistory;
use Corex\Config\Activity\ActivityTable;
use Corex\Database\Schema\Migrator;

/** Records one access event of this file's person. */
function historyEvent(int $actorId, string $kind, array $context, DateTimeImmutable $at, string $targetId = 'editor'): void
{
    Boot::app()->container()->make(ActivityService::class)->record(
        actorId: $actorId,
        actorKind: ActivityEvent::ACTOR_USER,
        actorLabel: 'History person',
        area: ActivityEvent::AREA_ACCESS,
        kind: $kind,
        targetType: str_starts_with($kind, 'access.role') ? 'role' : 'access_request',
        targetId: $targetId,
        targetLabel: $targetId,
        outcome: ActivityEvent::OUTCOME_SUCCESS,
        summary: ['key' => $kind, 'args' => []],
        context: $context,
        sensitivity: ActivityEvent::SENSITIVITY_SECURITY,
        retentionUntil: $at->modify('+180 days'),
        occurredAt: $at,
    );
}

beforeEach(function () {
    $this->person = wp_insert_user([
        'user_login' => 'corex-history-' . wp_generate_password(6, false),
        'user_pass'  => wp_generate_password(),
        'user_email' => uniqid('corex-history-', true) . '@example.test',
        'role'       => 'subscriber',
    ]);
    expect($this->person)->toBeInt();
    $this->keptAudit = get_option('corex_access_audit_log', []);
    $this->history   = Boot::app()->container()->make(AccessHistory::class);
});

afterEach(function () {
    global $wpdb;
    $wpdb->delete(Boot::app()->container()->make(Migrator::class)->fullName(ActivityTable::NAME), ['actor_id' => $this->person]);
    update_option('corex_access_audit_log', $this->keptAudit, false);

    require_once ABSPATH . 'wp-admin/includes/user.php';
    wp_delete_user($this->person);
});

it('lists role changes, refused changes, requests and refusals newest first, each in words with its kind', function () {
    $now = new DateTimeImmutable('now');
    historyEvent($this->person, 'access.role.changed', ['new_effects' => [CorexAbility::MANAGE_FORMS => 'allow']], $now->modify('-3 hours'));
    historyEvent($this->person, 'access.role.refused', ['blockers' => [['code' => 'site_administrator_role', 'ability' => CorexAbility::MANAGE_EMAIL]]], $now->modify('-2 hours'), 'administrator');
    historyEvent($this->person, 'access.request.created', ['ability_key' => CorexAbility::MANAGE_EMAIL], $now->modify('-1 hour'), '1');
    Boot::app()->container()->make(AccessAuditLog::class)->record('denied', 'corex-forms', $this->person, $now->getTimestamp());

    $page  = $this->history->page('', $this->person);
    $kinds = array_column($page['entries'], 'kind');
    $texts = array_column($page['entries'], 'text');

    expect($kinds)->toBe(['refused', 'requested', 'role_refused', 'role_changed'])
        ->and($texts[0])->toBe('Refused Forms & Flows')
        ->and($texts[1])->toBe('Asked for “Manage Email Studio”')
        ->and($texts[2])->toContain('site administrators always hold every CoreX ability')
        ->and($texts[3])->toContain('“Manage forms and flows” set to Allow')
        ->and(implode(' ', $texts))->not->toContain('corex_')
        ->and($page['entries'][1]['kindLabel'])->toBe('Access requested')
        ->and($page['people'])->toHaveKey($this->person);
});

it('filters by kind', function () {
    $now = new DateTimeImmutable('now');
    historyEvent($this->person, 'access.role.changed', ['new_effects' => []], $now);
    historyEvent($this->person, 'access.request.created', ['ability_key' => CorexAbility::MANAGE_FORMS], $now, '2');

    $page = $this->history->page('requested', $this->person);

    expect(array_column($page['entries'], 'kind'))->toBe(['requested']);
});

it('pages by 25, newest first', function () {
    $now = new DateTimeImmutable('now');
    for ($i = 0; $i < 30; $i++) {
        historyEvent($this->person, 'access.role.changed', ['new_effects' => []], $now->modify("-{$i} minutes"));
    }

    $first  = $this->history->page('', $this->person, 1);
    $second = $this->history->page('', $this->person, 2);

    expect($first['entries'])->toHaveCount(AccessHistory::PER_PAGE)
        ->and($first['pages'])->toBe(2)
        ->and($first['total'])->toBe(30)
        ->and($second['entries'])->toHaveCount(5)
        ->and($first['entries'][0]['at'])->toBeGreaterThan($second['entries'][0]['at']);
});

it('says why a decision on a request was refused (DECISIONS #428)', function () {
    $now = new DateTimeImmutable('now');
    $own = ['requester_id' => $this->person, 'abilities' => [CorexAbility::MANAGE_FORMS], 'blockers' => []];
    historyEvent($this->person, 'access.request.refused', $own, $now->modify('-2 hours'), '1');
    historyEvent($this->person, 'access.request.refused', [
        'requester_id' => $this->person,
        'abilities'    => [CorexAbility::MANAGE_ACCESS],
        'blockers'     => [['code' => 'critical_needs_site_administrator', 'ability' => CorexAbility::MANAGE_ACCESS]],
    ], $now->modify('-1 hour'), '2');

    $entries = $this->history->page('decision_refused', $this->person)['entries'];

    expect(array_column($entries, 'kindLabel'))->toBe(['Decision refused', 'Decision refused'])
        ->and($entries[0]['text'])->toContain('only a site administrator can allow it')
        ->and($entries[1]['text'])->toContain('Only a site administrator can decide their own access request.');
});
