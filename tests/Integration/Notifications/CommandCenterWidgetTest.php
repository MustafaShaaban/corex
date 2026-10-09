<?php

/**
 * Integration tests for the Dashboard Command Center widget (spec 072 Phase C: T023/T025, FR-015).
 *
 * Real WordPress. Proves the widget renders its rows and — critically — that rendering it makes NO
 * outbound HTTP request: the command center reads only local/stored state, never a remote check.
 *
 * @package Corex\Tests\Integration\Notifications
 */

declare(strict_types=1);

use Corex\Config\Notifications\CommandCenterWidget;
use Corex\Tests\Support\AdminScreen;
use Corex\Tests\Support\NotificationRows;

/** The prefix of every key the readiness producer writes. */
const READINESS_NOTIFICATION_DEDUP = 'readiness.blocker:';

beforeEach(function () {
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $this->widget = Corex\Boot::app()->container()->make(CommandCenterWidget::class);

    // Rendering evaluates readiness for real, and the readiness producer records what it finds:
    // this install's own blockers, under the keys the product uses. Those rows belong to the
    // install. A run must not add them, and must not raise the count or move the date on ones
    // already there, so they are handed back as they were.
    $this->readinessRows = NotificationRows::snapshotPrefixed(READINESS_NOTIFICATION_DEDUP);
});

afterEach(function () {
    NotificationRows::restorePrefixed(READINESS_NOTIFICATION_DEDUP, $this->readinessRows);

    // The registration test sets an admin screen (`dashboard`); restore a front-end screen so a later
    // test's is_admin() is not left true (which would, e.g., make MaintenanceGuard stop blocking).
    AdminScreen::set('front');
    wp_set_current_user(0);
});

it('registers a dashboard widget for an administrator', function () {
    // wp_add_dashboard_widget() is in the first and calls add_meta_box(), which is in the second.
    require_once ABSPATH . 'wp-admin/includes/dashboard.php';
    require_once ABSPATH . 'wp-admin/includes/template.php';
    AdminScreen::set('dashboard');
    global $wp_meta_boxes;
    unset($wp_meta_boxes['dashboard']);

    $this->widget->add();

    expect($wp_meta_boxes['dashboard']['normal']['core'] ?? [])->toHaveKey('corex_command_center');
});

it('renders site state, attention, and readiness with navigation-only links', function () {
    ob_start();
    $this->widget->render();
    $html = (string) ob_get_clean();

    expect($html)->toContain('corex-command-center')
        ->and($html)->toContain('page=corex-notifications')
        ->and(substr_count($html, 'corex-command-center__row'))->toBe(3);
});

it('makes no outbound HTTP request while rendering (FR-015)', function () {
    $attempts = [];
    $guard = static function ($pre, $args, $url) use (&$attempts) {
        $attempts[] = $url;

        return new WP_Error('blocked', 'No HTTP allowed during command-center render.');
    };
    add_filter('pre_http_request', $guard, 10, 3);

    ob_start();
    $this->widget->render();
    ob_end_clean();

    remove_filter('pre_http_request', $guard, 10);

    expect($attempts)->toBe([]);
});
