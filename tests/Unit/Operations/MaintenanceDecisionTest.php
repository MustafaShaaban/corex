<?php

/**
 * Unit tests for the one Maintenance decision the page guard and the submission guard share
 * (spec 112, User Story 3): who Maintenance closes the site to, whatever the request is.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Config\Operations\MaintenanceDecision;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

function maintenanceDecision(string $mode, bool $loggedIn = false, bool $canManage = false): MaintenanceDecision
{
    $GLOBALS['corex_test_options'] = ['corex_operations_mode' => $mode];
    Functions\when('get_option')->alias(static fn (string $key, $default = false) => $GLOBALS['corex_test_options'][$key] ?? $default);
    Functions\when('wp_get_environment_type')->justReturn('production');
    Functions\when('is_user_logged_in')->justReturn($loggedIn);
    Functions\when('current_user_can')->justReturn($canManage);

    return new MaintenanceDecision(new OperationsModeStore(new OperationsMode()));
}

it('closes the site in maintenance to everyone but an administrator', function (bool $loggedIn) {
    expect(maintenanceDecision(OperationsMode::MAINTENANCE, $loggedIn, false)->closedToCurrentVisitor())->toBeTrue();
})->with([
    'not signed in'                   => [false],
    'signed in, not an administrator' => [true],
]);

it('keeps the site open to a signed-in administrator', function () {
    expect(maintenanceDecision(OperationsMode::MAINTENANCE, true, true)->closedToCurrentVisitor())->toBeFalse();
});

it('keeps the site open in every other mode', function (string $mode) {
    expect(maintenanceDecision($mode)->closedToCurrentVisitor())->toBeFalse();
})->with([
    OperationsMode::DEVELOPMENT,
    OperationsMode::STAGING,
    OperationsMode::PRODUCTION,
    OperationsMode::COMING_SOON,
]);

it('honours the emergency bypass filter (DECISIONS #123)', function () {
    $decision = maintenanceDecision(OperationsMode::MAINTENANCE);
    Filters\expectApplied('corex_maintenance_bypass')->once()->andReturn(true);

    expect($decision->closedToCurrentVisitor())->toBeFalse();
});

it('decides the same whatever the request is, so a REST submission is not waved through', function () {
    // The page guard adds its own "never intercept admin, cron, AJAX or REST" rule on top; the
    // decision itself must not, or the submission guard would never close anything.
    Functions\when('is_admin')->justReturn(false);
    Functions\when('wp_doing_ajax')->justReturn(true);
    Functions\when('wp_doing_cron')->justReturn(true);

    expect(maintenanceDecision(OperationsMode::MAINTENANCE)->closedToCurrentVisitor())->toBeTrue();
});
