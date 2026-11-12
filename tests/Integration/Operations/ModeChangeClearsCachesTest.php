<?php

/**
 * A mode change clears the page caches, and its activity record says so (spec 124, FR-004b,
 * DECISIONS #524), on real WordPress.
 *
 * The change goes through {@see ModeChangeService}, the one the screen and the command line both
 * use. The install's stored mode is never written: a `pre_option_` filter answers the mode in
 * force, and a `pre_update_option_` filter keeps the stored value as it is, so the store does all
 * it does on a change (its history row, its announcement) and the row in the options table is
 * untouched. The history row, the security events start and the activity event this writes are
 * put back afterwards.
 *
 * LiteSpeed Cache is stood in for by a listener on its own purge action, `litespeed_purge`: it is
 * the one supported plugin whose purge is an action, so the stand-in needs no function defined for
 * the rest of the process.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Activity\ActivityEvent;
use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Activity\ActivitySentences;
use Corex\Config\Activity\SecurityActivityRecorder;
use Corex\Config\Activity\SecurityEventsSince;
use Corex\Config\Operations\ModeChangeRequest;
use Corex\Config\Operations\ModeChangeResult;
use Corex\Config\Operations\ModeChangeService;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

const COREX_TEST_MODE_LOG = 'corex_operations_mode_log';

/** The newest mode-changed activity event, or null. */
function corexNewestModeChange(): ?ActivityEvent
{
    return Boot::app()->container()->make(ActivityService::class)
        ->query(['kind' => SecurityActivityRecorder::MODE_CHANGED], 1, 1)[0] ?? null;
}

beforeEach(function () {
    global $wpdb;

    $this->storedMode = $wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", OperationsModeStore::OPTION));
    $this->logWas     = get_option(COREX_TEST_MODE_LOG, null);
    $this->sinceWas   = get_option(SecurityEventsSince::OPTION, null);
    $this->newestWas  = corexNewestModeChange()?->id ?? 0;

    $this->mode = OperationsMode::DEVELOPMENT;
    $this->answerMode = fn (): string => $this->mode;
    add_filter('pre_option_' . OperationsModeStore::OPTION, $this->answerMode);
    $this->keepStored = static fn ($value, $old) => $old;
    add_filter('pre_update_option_' . OperationsModeStore::OPTION, $this->keepStored, 10, 2);

    $this->purges = [];
    $this->litespeed = function (...$args): void {
        $this->purges[] = $args;
    };
    add_action('litespeed_purge', $this->litespeed);

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin = (int) ($administrators[0] ?? 0);
});

afterEach(function () {
    global $wpdb;

    remove_filter('pre_option_' . OperationsModeStore::OPTION, $this->answerMode);
    remove_filter('pre_update_option_' . OperationsModeStore::OPTION, $this->keepStored, 10);
    remove_action('litespeed_purge', $this->litespeed);

    foreach ([COREX_TEST_MODE_LOG => $this->logWas, SecurityEventsSince::OPTION => $this->sinceWas] as $option => $value) {
        $value === null ? delete_option($option) : update_option($option, $value, false);
    }
    $wpdb->query($wpdb->prepare(
        "DELETE FROM {$wpdb->prefix}corex_activity_events WHERE kind = %s AND id > %d",
        SecurityActivityRecorder::MODE_CHANGED,
        $this->newestWas,
    ));

    // The stored mode was never written.
    expect($wpdb->get_var($wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", OperationsModeStore::OPTION)))
        ->toBe($this->storedMode);
});

function corexChangeMode(string $mode, int $actor): ModeChangeResult
{
    return Boot::app()->container()->make(ModeChangeService::class)
        ->apply(new ModeChangeRequest($mode, $actor, new DateTimeImmutable('now')));
}

it('FR-004b: asks an active page-cache plugin to clear every page when the mode changes', function () {
    $result = corexChangeMode(OperationsMode::STAGING, $this->admin);

    expect($result->status)->toBe(ModeChangeResult::SAVED)
        ->and($this->purges)->toBe([['*']]);
});

it('FR-004b: says in the activity log which page caches the change cleared', function () {
    corexChangeMode(OperationsMode::STAGING, $this->admin);

    $event = corexNewestModeChange();
    expect($event)->not->toBeNull()
        ->and($event->id)->toBeGreaterThan($this->newestWas)
        ->and($event->context['page_caches'])->toBe(['cleared' => ['litespeed-cache'], 'failed' => []])
        ->and(Boot::app()->container()->make(ActivitySentences::class)->of($event)['text'])
        ->toEndWith('changed the mode to Staging and cleared the page cache of LiteSpeed Cache');
});

it('FR-004b: changes the mode, and records that the cache could not be cleared, when a purge throws', function () {
    remove_action('litespeed_purge', $this->litespeed);
    $this->litespeed = static function (): void {
        throw new RuntimeException('The cache server did not answer.');
    };
    add_action('litespeed_purge', $this->litespeed);
    $announced = did_action('corex_operations_mode_changed');

    $result = corexChangeMode(OperationsMode::STAGING, $this->admin);

    expect($result->status)->toBe(ModeChangeResult::SAVED)
        ->and(did_action('corex_operations_mode_changed'))->toBe($announced + 1)
        ->and(corexNewestModeChange()?->context['page_caches'])->toBe(['cleared' => [], 'failed' => ['litespeed-cache']]);
});

it('FR-004b: clears nothing when the mode asked for is already in force', function () {
    $result = corexChangeMode(OperationsMode::DEVELOPMENT, $this->admin);

    expect($result->status)->toBe(ModeChangeResult::UNCHANGED)
        ->and($this->purges)->toBe([]);
});
