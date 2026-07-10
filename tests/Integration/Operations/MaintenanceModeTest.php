<?php

/**
 * Integration tests for the real Maintenance mode guard.
 *
 * @package Corex\Tests\Integration\Operations
 */

declare(strict_types=1);

use Corex\Config\Operations\MaintenanceGuard;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

const COREX_TEST_OPERATIONS_MODE_OPTION = 'corex_operations_mode';

beforeEach(function () {
    $this->previousMode = get_option(COREX_TEST_OPERATIONS_MODE_OPTION, null);
    update_option(COREX_TEST_OPERATIONS_MODE_OPTION, OperationsMode::MAINTENANCE);
    wp_set_current_user(0);

    $this->guard = new MaintenanceGuard(new OperationsModeStore(new OperationsMode()));
});

afterEach(function () {
    remove_all_filters('corex_maintenance_bypass');
    wp_set_current_user(0);

    if ($this->previousMode === null) {
        delete_option(COREX_TEST_OPERATIONS_MODE_OPTION);
    } else {
        update_option(COREX_TEST_OPERATIONS_MODE_OPTION, $this->previousMode);
    }
});

it('registers on template_redirect before theme output', function () {
    $this->guard->register();

    expect(has_action('template_redirect', [$this->guard, 'maybeBlock']))->toBe(0);
});

it('blocks anonymous front-end visitors while maintenance mode is active', function () {
    expect($this->guard->shouldBlock())->toBeTrue();
});

it('does not block a signed-in administrator, preserving the recovery path', function () {
    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($adminId);

    expect(is_user_logged_in())->toBeTrue()
        ->and(current_user_can('manage_options'))->toBeTrue()
        ->and($this->guard->shouldBlock())->toBeFalse();
});

it('allows an explicit emergency bypass filter without changing stored mode', function () {
    add_filter('corex_maintenance_bypass', '__return_true');

    expect($this->guard->shouldBlock())->toBeFalse()
        ->and(get_option(COREX_TEST_OPERATIONS_MODE_OPTION))->toBe(OperationsMode::MAINTENANCE);
});
