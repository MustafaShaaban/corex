<?php

/**
 * Unit tests for the Maintenance guard decision (spec 065) — the lockout-prevention contract.
 * A signed-in administrator (or any admin/cron/AJAX/REST context) is never blocked.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Operations\MaintenanceGuard;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

function maintenanceGuard(string $mode): MaintenanceGuard
{
    Functions\when('__')->returnArg();
    $GLOBALS['corex_test_options'] = ['corex_operations_mode' => $mode];
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('wp_get_environment_type')->justReturn('production');

    return new MaintenanceGuard(new OperationsModeStore(new OperationsMode()));
}

function maintenanceContext(bool $isAdmin, bool $loggedIn, bool $canManage): void
{
    Functions\when('is_admin')->justReturn($isAdmin);
    Functions\when('wp_doing_cron')->justReturn(false);
    Functions\when('wp_doing_ajax')->justReturn(false);
    Functions\when('is_user_logged_in')->justReturn($loggedIn);
    Functions\when('current_user_can')->justReturn($canManage);
}

it('does not block when the mode is not maintenance', function () {
    $guard = maintenanceGuard(OperationsMode::PRODUCTION);
    maintenanceContext(false, false, false);

    expect($guard->shouldBlock())->toBeFalse();
});

it('blocks an anonymous front-end visitor in maintenance mode', function () {
    $guard = maintenanceGuard(OperationsMode::MAINTENANCE);
    maintenanceContext(false, false, false);

    expect($guard->shouldBlock())->toBeTrue();
});

it('never blocks a signed-in administrator (no lockout)', function () {
    $guard = maintenanceGuard(OperationsMode::MAINTENANCE);
    maintenanceContext(false, true, true);

    expect($guard->shouldBlock())->toBeFalse();
});

it('never intercepts the wp-admin context', function () {
    $guard = maintenanceGuard(OperationsMode::MAINTENANCE);
    maintenanceContext(true, false, false);

    expect($guard->shouldBlock())->toBeFalse();
});

it('blocks a logged-in non-admin visitor in maintenance mode', function () {
    $guard = maintenanceGuard(OperationsMode::MAINTENANCE);
    maintenanceContext(false, true, false);

    expect($guard->shouldBlock())->toBeTrue();
});

it('builds a branded maintenance card body (no bare wp_die notice)', function () {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    // An empty site name exercises the brand-name fallback in the eyebrow. get_bloginfo is
    // stubbed (not left undefined) because earlier suites may already have defined it.
    Functions\when('get_bloginfo')->justReturn('');

    $html = maintenanceGuard(OperationsMode::MAINTENANCE)->bodyHtml();

    expect($html)->toContain('corex-standalone__card')
        ->and($html)->toContain('corex-standalone__mark')
        ->and($html)->toContain('We’ll be back soon')
        ->and($html)->toContain('Scheduled maintenance')
        // With no site name, the eyebrow falls back to the brand.
        ->and($html)->toContain('Corex');
});
