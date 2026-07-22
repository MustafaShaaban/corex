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
use Corex\Config\Security\LoginProtection\LoginSlug;
use Corex\Config\Security\LoginProtection\WpLoginAttemptStore;

beforeEach(function () {
    $this->previousLoginSettings = get_option(LoginProtectionSettingsStore::OPTION, null);

    // LoginRouteGuard matches the custom slug by PATH under pretty permalinks and by QUERY STRING
    // under plain ones, so these tests silently depended on whatever the host site had configured:
    // they passed on a dev install with pretty permalinks and failed on a fresh WordPress, which is
    // what CI provisions. Pin it — the subject here is the break-glass, not the permalink mode.
    $this->previousPermalinks = get_option('permalink_structure');
    update_option('permalink_structure', '/%postname%/');
});

afterEach(function () {
    if (property_exists($this, 'previousPermalinks')) {
        update_option('permalink_structure', $this->previousPermalinks);
    }

    if (! property_exists($this, 'previousLoginSettings')) {
        return;
    }

    if ($this->previousLoginSettings === null) {
        delete_option(LoginProtectionSettingsStore::OPTION);
    } else {
        update_option(LoginProtectionSettingsStore::OPTION, $this->previousLoginSettings);
    }
});

it('stops hiding the default endpoints while the unguard break-glass is active', function () {
    update_option(LoginProtectionSettingsStore::OPTION, [
        'enabled' => true,
        'custom_slug' => 'team-login',
        'block_default_endpoints' => true,
    ]);
    $settings = (new LoginProtectionSettingsStore())->current();

    $guarded = new LoginRouteGuard($settings, unguarded: false);
    $unguarded = new LoginRouteGuard($settings, unguarded: true);

    expect($guarded->entryPointFor('/wp-login.php', isAdmin: false))->toBe('hide')
        ->and($unguarded->entryPointFor('/wp-login.php', isAdmin: false))->toBe('pass')
        ->and($unguarded->hidesAdminArea(isAdmin: true, loggedIn: false, ajax: false, script: 'index.php', path: '/wp-admin/'))->toBeFalse()
        // The slug keeps working during recovery — the break-glass restores the default, it does
        // not tear down the custom entrance an owner may still be using.
        ->and($unguarded->entryPointFor('/team-login/', isAdmin: false))->toBe('serve_login');
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

    // Register the guard's rewriting filters, exactly as a real WP-CLI run has them: the command
    // executes in a booted process where the guard is already live. Without this the test cannot
    // see the bug it exists to catch — the reported URL was the custom slug, which 404s the moment
    // the command disables it, and the assertion below passed anyway (DECISIONS #140).
    $guard = new LoginRouteGuard((new LoginProtectionSettingsStore())->current());
    $guard->register();

    try {
        $result = (new SecurityResetLoginCommand($store))->restore($now);
    } finally {
        // Detach before asserting: these filters are global, and leaving them attached rewrites
        // site_url() for every test that runs after this one.
        remove_filter('site_url', [$guard, 'filterSiteUrl'], 20);
        remove_filter('network_site_url', [$guard, 'filterSiteUrl'], 20);

        foreach (['login_url', 'logout_url', 'lostpassword_url', 'register_url', 'wp_redirect', 'site_option_welcome_email'] as $hook) {
            remove_filter($hook, [$guard, 'filterLoginUrl'], 20);
        }
    }
    $after = get_option(LoginProtectionSettingsStore::OPTION);
    $afterHash = (string) $wpdb->get_var($wpdb->prepare('SELECT user_pass FROM ' . $wpdb->users . ' WHERE ID = %d', $adminId));

    expect($result['restored_login_url'])->toContain('wp-login.php')
        ->and($result['restored_login_url'])->not->toContain('team-login')
        ->and($result['released_lockouts'])->toBeGreaterThanOrEqual(1)
        ->and($after['enabled'])->toBeFalse()
        ->and($after['block_default_endpoints'])->toBeFalse()
        // The slug resets too, so re-enabling protection cannot walk back into the same lockout.
        ->and($after['custom_slug'])->toBe(LoginSlug::DEFAULT)
        ->and($afterHash)->toBe($beforeHash);
});
