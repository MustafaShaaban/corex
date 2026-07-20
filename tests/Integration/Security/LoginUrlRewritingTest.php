<?php

/**
 * Integration tests for login URL rewriting against real WordPress.
 *
 * Hiding wp-login.php from everyone is only safe because nothing points at it any more. That makes
 * these rewrites load-bearing rather than cosmetic: if one of them regresses, a legitimate flow
 * lands on a 404 that looks exactly like a missing page, with no clue as to why.
 *
 * Real core functions are used throughout — a stub could not prove that core's own URL builders
 * come out rewritten, which is the entire claim.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;

/**
 * Detach exactly this guard's filters.
 *
 * Deliberately not remove_all_filters(): that strips core's and every other plugin's callbacks
 * from the same hooks, which leaks out of this file and fails unrelated suites (it broke the forms
 * submission test, which builds URLs through site_url).
 */
function corexUnregisterGuard(LoginRouteGuard $guard): void
{
    remove_filter('site_url', [$guard, 'filterSiteUrl'], 20);
    remove_filter('network_site_url', [$guard, 'filterSiteUrl'], 20);

    foreach (['login_url', 'logout_url', 'lostpassword_url', 'register_url', 'wp_redirect', 'site_option_welcome_email'] as $hook) {
        remove_filter($hook, [$guard, 'filterLoginUrl'], 20);
    }
}

beforeEach(function () {
    $this->previousLoginSettings = get_option(LoginProtectionSettingsStore::OPTION, null);

    update_option(LoginProtectionSettingsStore::OPTION, [
        'enabled' => true,
        'custom_slug' => 'team-login',
        'block_default_endpoints' => true,
    ], false);

    $this->guard = new LoginRouteGuard((new LoginProtectionSettingsStore())->current());
    $this->guard->register();
});

afterEach(function () {
    corexUnregisterGuard($this->guard);

    if ($this->previousLoginSettings === null) {
        delete_option(LoginProtectionSettingsStore::OPTION);

        return;
    }

    update_option(LoginProtectionSettingsStore::OPTION, $this->previousLoginSettings);
});

it('rewrites the login URL core hands to visitors', function () {
    expect(wp_login_url())->toContain('team-login')
        ->and(wp_login_url())->not->toContain('wp-login.php');
});

it('keeps the redirect_to argument when rewriting the login URL', function () {
    // wp_login_url($redirect) is how core sends someone to log in and back again. Dropping the
    // argument would strand them on the login screen after signing in.
    $url = wp_login_url('https://example.test/members/');

    expect($url)->toContain('team-login')
        ->and($url)->toContain('redirect_to')
        ->and($url)->toContain(rawurlencode('https://example.test/members/'));
});

it('rewrites the logout URL and keeps its nonce and action intact', function () {
    // The old str_replace preserved these by luck. Losing _wpnonce breaks logout outright.
    $url = wp_logout_url('https://example.test/bye/');

    expect($url)->toContain('team-login')
        ->and($url)->not->toContain('wp-login.php')
        ->and($url)->toContain('action=logout')
        ->and($url)->toContain('_wpnonce=');
});

it('rewrites the lost-password and register URLs', function () {
    expect(wp_lostpassword_url())->toContain('team-login')
        ->and(wp_lostpassword_url())->not->toContain('wp-login.php')
        ->and(wp_registration_url())->toContain('team-login')
        ->and(wp_registration_url())->not->toContain('wp-login.php');
});

it('rewrites the post-password form action core builds through site_url', function () {
    // wp-includes/post-template.php builds this as site_url('wp-login.php?action=postpass',
    // 'login_post'). It is the reason a password-protected post still works while wp-login.php is
    // hidden from logged-in users too.
    $action = site_url('wp-login.php?action=postpass', 'login_post');

    expect($action)->toContain('team-login')
        ->and($action)->not->toContain('wp-login.php')
        ->and($action)->toContain('action=postpass');
});

it('rewrites a redirect that any component aims at the default login', function () {
    // Nothing filtered wp_redirect before, so a stale or third-party redirect to wp-login.php
    // landed on the hidden endpoint.
    $location = apply_filters('wp_redirect', site_url('wp-login.php?loggedout=true'), 302);

    expect($location)->toContain('team-login')
        ->and($location)->not->toContain('wp-login.php')
        ->and($location)->toContain('loggedout=true');
});

it('rewrites the login address inside the multisite welcome email without mangling the message', function () {
    // This filter passes a whole message body, not a URL. Routing it through the URL rewriter
    // (which parses its input as a URL) would replace the entire email with a single address.
    $email = "Welcome!\n\nLog in here: BLOG_URLwp-login.php\n\nThanks,\nThe Team";

    $filtered = apply_filters('site_option_welcome_email', $email);

    expect($filtered)->toContain('team-login')
        ->and($filtered)->not->toContain('wp-login.php')
        ->and($filtered)->toContain('Welcome!')
        ->and($filtered)->toContain('Thanks,')
        ->and($filtered)->toContain('BLOG_URL');
});

it('leaves URLs that have nothing to do with the login alone', function () {
    expect(home_url('/about/'))->not->toContain('team-login')
        ->and(admin_url('edit.php'))->toContain('wp-admin/edit.php')
        ->and(admin_url('edit.php'))->not->toContain('team-login')
        ->and(site_url('wp-cron.php'))->toContain('wp-cron.php');
});

it('does not rewrite anything once protection is switched off', function () {
    corexUnregisterGuard($this->guard);

    update_option(LoginProtectionSettingsStore::OPTION, [
        'enabled' => false,
        'custom_slug' => 'team-login',
        'block_default_endpoints' => true,
    ], false);

    // Kept on $this so afterEach detaches whichever guard ended up registered.
    $this->guard = new LoginRouteGuard((new LoginProtectionSettingsStore())->current());
    $this->guard->register();

    expect(wp_login_url())->toContain('wp-login.php')
        ->and(wp_login_url())->not->toContain('team-login');
});
