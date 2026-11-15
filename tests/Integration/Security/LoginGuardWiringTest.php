<?php

/**
 * What `LoginRouteGuard::register()` hooks for each switch, on real WordPress and with no HTTP, so
 * it runs where no web server answers (spec 124, FR-013, slice 4 review).
 *
 * The answers over HTTP are in LoginSwitchesOverHttpTest, which skips on CI's integration job. This
 * holds the wiring those answers come from: the own address and the URL filters whenever a switch
 * is on, the shortcut redirects (`wp_redirect_admin_locations`) taken off only when wp-login.php is
 * hidden, and the admin request sealed only when the admin is hidden. Every hook it adds is taken
 * off afterwards, and core's redirect is put back where it was.
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Config\Security\LoginProtection\LoginProtectionSettings;
use Corex\Config\Security\LoginProtection\LoginRouteGuard;
use Corex\Config\Security\LoginProtection\LoginSwitches;

/** @return array<string,mixed> what register() leaves on each hook it can touch */
function corexGuardWiring(LoginRouteGuard $guard): array
{
    return [
        'serves'        => has_action('wp_loaded', [$guard, 'serveRequest']),
        'site_url'      => has_filter('site_url', [$guard, 'filterSiteUrl']),
        'network_url'   => has_filter('network_site_url', [$guard, 'filterSiteUrl']),
        'login_url'     => has_filter('login_url', [$guard, 'filterLoginUrl']),
        'logout_url'    => has_filter('logout_url', [$guard, 'filterLoginUrl']),
        'lost_password' => has_filter('lostpassword_url', [$guard, 'filterLoginUrl']),
        'register_url'  => has_filter('register_url', [$guard, 'filterLoginUrl']),
        'wp_redirect'   => has_filter('wp_redirect', [$guard, 'filterLoginUrl']),
        'welcome_email' => has_filter('site_option_welcome_email', [$guard, 'filterWelcomeEmail']),
        'shortcuts'     => has_action('template_redirect', 'wp_redirect_admin_locations'),
        'seal'          => has_action('setup_theme', [$guard, 'sealHiddenAdminRequest']),
    ];
}

function corexUnhookGuard(LoginRouteGuard $guard): void
{
    remove_action('wp_loaded', [$guard, 'serveRequest'], 1);
    remove_filter('site_url', [$guard, 'filterSiteUrl'], 20);
    remove_filter('network_site_url', [$guard, 'filterSiteUrl'], 20);
    foreach (['login_url', 'logout_url', 'lostpassword_url', 'register_url', 'wp_redirect'] as $hook) {
        remove_filter($hook, [$guard, 'filterLoginUrl'], 20);
    }
    remove_filter('site_option_welcome_email', [$guard, 'filterWelcomeEmail'], 20);
    remove_action('setup_theme', [$guard, 'sealHiddenAdminRequest'], 1);
}

beforeEach(function () {
    global $pagenow;

    $this->server = $_SERVER;
    $this->pagenow = $pagenow;
    // An ordinary front-end request: nothing for captureRequest() to serve or hide.
    $_SERVER['REQUEST_URI'] = '/';
    $this->shortcuts = has_action('template_redirect', 'wp_redirect_admin_locations');
    $this->guard = null;
});

afterEach(function () {
    global $pagenow;

    if ($this->guard instanceof LoginRouteGuard) {
        corexUnhookGuard($this->guard);
    }
    if ($this->shortcuts !== false && has_action('template_redirect', 'wp_redirect_admin_locations') === false) {
        add_action('template_redirect', 'wp_redirect_admin_locations', $this->shortcuts);
    }
    $_SERVER = $this->server;
    $pagenow = $this->pagenow;
});

it('FR-013: hooks the own address, the shortcuts and the admin seal by switch', function (bool $login, bool $admin, bool $limits) {
    // Core hooks the shortcuts itself; without them there is nothing to see removed.
    expect($this->shortcuts)->toBe(1000);

    $this->guard = new LoginRouteGuard(LoginProtectionSettings::of(new LoginSwitches($login, $admin, $limits), customSlug: 'team-entry'));
    $this->guard->register();
    $wiring = corexGuardWiring($this->guard);
    $anyOn = $login || $admin || $limits;

    expect($wiring['serves'])->toBe($anyOn ? 1 : false)
        ->and($wiring['site_url'])->toBe($anyOn ? 20 : false)
        ->and($wiring['network_url'])->toBe($anyOn ? 20 : false)
        ->and($wiring['login_url'])->toBe($anyOn ? 20 : false)
        ->and($wiring['logout_url'])->toBe($anyOn ? 20 : false)
        ->and($wiring['lost_password'])->toBe($anyOn ? 20 : false)
        ->and($wiring['register_url'])->toBe($anyOn ? 20 : false)
        ->and($wiring['wp_redirect'])->toBe($anyOn ? 20 : false)
        ->and($wiring['welcome_email'])->toBe($anyOn ? 20 : false)
        // Taken off whole with wp-login.php hidden, so /login, /admin and /dashboard are not found
        // (DECISIONS #628, amended).
        ->and($wiring['shortcuts'])->toBe($login ? false : 1000)
        ->and($wiring['seal'])->toBe($admin ? 1 : false);
})->with([
    'wp-login.php hidden alone' => [true, false, false],
    'the admin hidden alone' => [false, true, false],
    'both hidden' => [true, true, false],
    'neither hidden, limits on' => [false, false, true],
    'every switch off' => [false, false, false],
]);

it('FR-013, scenario 7: recovery hooks neither the shortcuts removal nor the seal, and keeps the own address', function () {
    $this->guard = new LoginRouteGuard(LoginProtectionSettings::of(new LoginSwitches(true, true, true), customSlug: 'team-entry'), true);
    $this->guard->register();
    $wiring = corexGuardWiring($this->guard);

    expect($wiring['serves'])->toBe(1)
        ->and($wiring['login_url'])->toBe(20)
        ->and($wiring['shortcuts'])->toBe(1000)
        ->and($wiring['seal'])->toBeFalse();
});
