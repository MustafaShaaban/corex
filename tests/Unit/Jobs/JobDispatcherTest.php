<?php

/**
 * Unit tests for optional Action Scheduler and WP-Cron bounded-job dispatchers.
 *
 * @package Corex\Tests\Unit\Jobs
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Jobs\ActionSchedulerJobDispatcher;
use Corex\Config\Jobs\CronJobDispatcher;
use Corex\Jobs\BoundedJob;

if (! function_exists('as_enqueue_async_action')) {
    function as_enqueue_async_action(string $hook, array $args, string $group): int
    {
        $GLOBALS['corex_action_scheduler_enqueue'] = compact('hook', 'args', 'group');

        return 1;
    }
}

if (! function_exists('as_unschedule_all_actions')) {
    function as_unschedule_all_actions(string $hook, array $args, string $group): int
    {
        $GLOBALS['corex_action_scheduler_cancel'] = compact('hook', 'args', 'group');

        return 1;
    }
}

function dispatcherJob(): BoundedJob
{
    return BoundedJob::queued(
        'data.export',
        7,
        10,
        hash('sha256', 'dispatcher-job'),
        new DateTimeImmutable('+1 minute'),
    )->withId(21);
}

it('dispatches and cancels through Action Scheduler when available', function () {
    $dispatcher = new ActionSchedulerJobDispatcher();

    expect($dispatcher->available())->toBeTrue();
    $dispatcher->dispatch(dispatcherJob());
    $dispatcher->cancel(21);

    expect($GLOBALS['corex_action_scheduler_enqueue'])->toBe([
        'hook'  => ActionSchedulerJobDispatcher::HOOK,
        'args'  => [21],
        'group' => ActionSchedulerJobDispatcher::GROUP,
    ])->and($GLOBALS['corex_action_scheduler_cancel'])->toBe([
        'hook'  => ActionSchedulerJobDispatcher::HOOK,
        'args'  => [21],
        'group' => ActionSchedulerJobDispatcher::GROUP,
    ]);
});
it('schedules one cron event and clears matching retries', function () {
    Functions\when('wp_next_scheduled')->justReturn(false);
    Functions\expect('wp_schedule_single_event')
        ->once()
        ->with(\Mockery::type('int'), CronJobDispatcher::HOOK, [21])
        ->andReturn(true);
    Functions\expect('wp_clear_scheduled_hook')
        ->once()
        ->with(CronJobDispatcher::HOOK, [21])
        ->andReturn(1);

    $dispatcher = new CronJobDispatcher();
    $dispatcher->dispatch(dispatcherJob());
    $dispatcher->cancel(21);

    expect($dispatcher->available())->toBeTrue();
});
