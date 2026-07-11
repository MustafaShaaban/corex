<?php

/**
 * Integration tests for login-protection recovery paths.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Cli\Commands\SecurityResetLoginCommand;
use Corex\Config\Security\LoginProtection\LoginAttemptRecord;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;
use Corex\Config\Security\LoginProtection\WpLoginAttemptStore;

beforeEach(function () {
    $this->previousLoginSettings = get_option(LoginProtectionSettingsStore::OPTION, null);
});

afterEach(function () {
    if (! property_exists($this, 'previousLoginSettings')) {
        return;
    }

    if ($this->previousLoginSettings === null) {
        delete_option(LoginProtectionSettingsStore::OPTION);
    } else {
        update_option(LoginProtectionSettingsStore::OPTION, $this->previousLoginSettings);
    }
});

it('reports the unguard constant as an immediate protected-route bypass', function () {
    $settings = (new LoginProtectionSettingsStore())->current();
    $guard = new LoginRouteGuard($settings);

    expect($guard->decision('/wp-login.php', authenticated: false, unguarded: true)->blocked)->toBeFalse();
});

it('resets protected login settings and releases active lockouts without changing users or passwords', function () {
    global $wpdb;

    update_option(LoginProtectionSettingsStore::OPTION, [
        'enabled' => true,
        'custom_slug' => 'team-login',
        'block_default_endpoints' => true,
        'threshold' => 3,
        'window_seconds' => 300,
        'lockout_seconds' => 900,
        'trusted_proxy_mode' => false,
        'trusted_proxy_ranges' => [],
        'retain_days' => 30,
        'successful_login_logging' => true,
    ]);

    $adminId = (int) get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    $beforeHash = (string) $wpdb->get_var($wpdb->prepare('SELECT user_pass FROM ' . $wpdb->users . ' WHERE ID = %d', $adminId));

    $store = \Corex\Boot::app()->container()->make(WpLoginAttemptStore::class);
    $now = new DateTimeImmutable('2026-07-07T12:00:00+00:00');
    $store->record(new LoginAttemptRecord(
        identityHash: hash('sha256', 'owner@example.com'),
        networkHash: hash('sha256', '203.0.113.9'),
        outcome: LoginAttemptRecord::LOCKED,
        reasonCode: 'threshold_exceeded',
        userId: null,
        occurredAt: $now,
        retentionUntil: $now->modify('+30 days'),
        lockedUntil: $now->modify('+15 minutes'),
    ));

    $result = (new SecurityResetLoginCommand($store))->restore($now);
    $after = get_option(LoginProtectionSettingsStore::OPTION);
    $afterHash = (string) $wpdb->get_var($wpdb->prepare('SELECT user_pass FROM ' . $wpdb->users . ' WHERE ID = %d', $adminId));

    expect($result['restored_login_url'])->toContain('wp-login.php')
        ->and($result['released_lockouts'])->toBeGreaterThanOrEqual(1)
        ->and($after['enabled'])->toBeFalse()
        ->and($after['block_default_endpoints'])->toBeFalse()
        ->and($afterHash)->toBe($beforeHash);
});
