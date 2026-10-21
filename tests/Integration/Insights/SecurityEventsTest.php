<?php

/**
 * The Insights security events card shows the security events that happened (spec 114,
 * US-SAFE-13), on real WordPress.
 *
 * Mode changes, refusals and lockouts were kept only in their own logs, and the card read the
 * twenty newest activity events of every area and kept the security ones: it said "No security
 * events recorded" on a site that had them, and twenty trashed submissions hid the rest.
 *
 * @package Corex\Tests\Integration\Insights
 */

declare(strict_types=1);

use Corex\Access\AccessRefusalRecorder;
use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Access\AccessAuditLog;
use Corex\Config\Activity\SecurityActivityRecorder;
use Corex\Config\Activity\SecurityEventsSince;
use Corex\Config\Insights\InsightWidgetFacts;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;
use Corex\Events\EventDispatcher;
use Corex\Security\LoginLockoutEvent;

beforeEach(function () {
    $this->modeWas  = get_option(OperationsModeStore::OPTION, null);
    $this->logWas   = get_option('corex_operations_mode_log', null);
    $this->auditWas = get_option('corex_access_audit_log', null);
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin    = (int) ($administrators[0] ?? 0);
    $this->activity = Boot::app()->container()->make(ActivityService::class);
});

afterEach(function () {
    foreach ([OperationsModeStore::OPTION => $this->modeWas, 'corex_operations_mode_log' => $this->logWas, 'corex_access_audit_log' => $this->auditWas] as $option => $value) {
        $value === null ? delete_option($option) : update_option($option, $value);
    }
});

/** The kinds the card shows, newest first. */
function cardKinds(): array
{
    $events = Boot::app()->container()->make(ActivityService::class)->query(['area' => [
        ActivityEvent::AREA_SECURITY,
        ActivityEvent::AREA_ACCESS,
        ActivityEvent::AREA_OPERATIONS,
    ]], 1, 5);

    return array_map(static fn (ActivityEvent $event): string => $event->kind, $events);
}

/** Something that happened somewhere else in CoreX: a trashed submission. */
function unrelatedEvent(ActivityService $activity): void
{
    $now = new DateTimeImmutable('now');
    $activity->record(1, ActivityEvent::ACTOR_USER, 'admin', ActivityEvent::AREA_SUBMISSIONS, 'submission.trashed', 'submission', '1', 'Contact', ActivityEvent::OUTCOME_SUCCESS, ['key' => 'submission.trashed', 'args' => []], [], ActivityEvent::SENSITIVITY_PUBLIC_ADMIN, $now->modify('+1 day'), $now);
}

it('records a mode change, a refusal, a lockout and an access decision, and keeps them on the card after thirty other events', function () {
    $store = Boot::app()->container()->make(OperationsModeStore::class);
    $store->set($store->current() === OperationsMode::STAGING ? OperationsMode::DEVELOPMENT : OperationsMode::STAGING, $this->admin);
    Boot::app()->container()->make(AccessRefusalRecorder::class)->refused($this->admin + 1000, 'denied', 'corex-data');
    Boot::app()->container()->make(EventDispatcher::class)->dispatch(new LoginLockoutEvent('someone', '203.0.113.9', new DateTimeImmutable('+15 minutes')));
    $now = new DateTimeImmutable('now');
    $this->activity->record($this->admin, ActivityEvent::ACTOR_USER, 'admin', ActivityEvent::AREA_ACCESS, 'access.request.approved', 'access_request', '7', 'Data', ActivityEvent::OUTCOME_SUCCESS, ['key' => 'access.request.approved', 'args' => []], [], ActivityEvent::SENSITIVITY_SECURITY, $now->modify('+1 day'), $now);

    for ($i = 0; $i < 30; $i++) {
        unrelatedEvent($this->activity);
    }

    expect(cardKinds())->toContain(SecurityActivityRecorder::MODE_CHANGED)
        ->and(cardKinds())->toContain(SecurityActivityRecorder::ACCESS_DENIED)
        ->and(cardKinds())->toContain(SecurityActivityRecorder::LOGIN_LOCKED)
        ->and(cardKinds())->toContain('access.request.approved');

    $lockout = Boot::app()->container()->make(ActivityService::class)->query(['kind' => SecurityActivityRecorder::LOGIN_LOCKED], 1, 1)[0];
    // When the lock ends, and not the username typed or the visitor's address.
    expect(array_keys($lockout->context))->toBe(['locked_until'])
        ->and($lockout->targetLabel)->toBe('Sign-in')
        ->and(wp_json_encode($lockout))->not->toContain('someone')
        ->and(wp_json_encode($lockout))->not->toContain('203.0.113.9');

    $card = Boot::app()->container()->make(InsightWidgetFacts::class)->gather()['securityEvents'];
    expect($card)->toHaveCount(5)
        ->and($card[0]['text'])->toBe('admin approved an access request');
});

it('adds one event for a counted refusal, not one per repeat inside its hour', function () {
    $refusals = static fn (): int => count(Boot::app()->container()->make(ActivityService::class)->query(['kind' => SecurityActivityRecorder::ACCESS_DENIED], 1, 100));
    $before   = $refusals();
    $log      = Boot::app()->container()->make(AccessAuditLog::class);

    for ($i = 0; $i < 5; $i++) {
        $log->record('denied', 'corex-insights', $this->admin + 2000);
    }

    expect($refusals())->toBe($before + 1);
});

it('says from when it has records', function () {
    $since = Boot::app()->container()->make(SecurityEventsSince::class)->mark();

    expect($since)->toBeGreaterThan(0)->toBeLessThanOrEqual(time())
        // Written once: a later mark keeps the first.
        ->and(Boot::app()->container()->make(SecurityEventsSince::class)->mark(time() + 3600))->toBe($since);
});
