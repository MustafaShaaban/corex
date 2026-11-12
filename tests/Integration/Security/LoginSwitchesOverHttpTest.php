<?php

/**
 * The three login switches, and the settings the previous release stored, answered by the running
 * site over HTTP (spec 124, FR-013, FR-014, SC-002, User Story 6 scenarios 1, 2, 3, 5 and 6).
 *
 * Each test stores the login settings, asks the site this suite runs against for its addresses as a
 * signed-out visitor would (no redirect followed), and reads the status, the `Location` and whether
 * the sign-in form is in the page. The site's own row is put back after every test, as it was found:
 * a hidden login is never left behind, even when a test fails (and, should the process die, by a
 * shutdown function). The sign-in attempts, notices and activity events a test causes are removed,
 * and only those; the account a test signs in with is deleted. No email is involved: a sign-in and a
 * lockout send none, and anything this process is asked to send is kept here by `pre_wp_mail`.
 *
 * SC-002: the previous release's answers below were read from this site running the previous
 * release's code (commit 7500f390), before this slice changed it, for each of the four shapes its
 * save could store. Neither client records its stored option; both record behaviour that only
 * "protection on, hiding on" with the address `corex-login` and the default limits produces
 * (Muva: `sites/muva/docs/client-guide/guide.md`, "mu-va.com/corex-login/", and `/wp-login.php`
 * answering 404 in `sites/muva/PROGRESS.md`; Perego: `sites/perego/docs/hosting.md`, "Sign-in:
 * `/corex-login/`. `/wp-login.php` and logged-out `/wp-admin/` answer 404", with 5 failures in 5
 * minutes and a 15-minute lockout in `sites/perego/PROGRESS.md`; `trusted_proxy_mode` off in both).
 *
 * @package Corex\Tests\Integration\Security
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Activity\SecurityActivityRecorder;
use Corex\Config\Notifications\Lockouts\LoginLockoutNotice;
use Corex\Config\Notifications\NotificationTable;
use Corex\Config\Security\LoginProtection\LoginAttemptTable;
use Corex\Config\Security\LoginProtection\LoginProtectionPolicy;
use Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore;
use Corex\Database\Schema\Migrator;
use Corex\Tests\Support\OptionRow;

/** The row as this file found it, for the shutdown function. */
$GLOBALS['corex_login_switches_http_row'] = null;

/**
 * What a signed-out visitor gets at an address: the status, where it sends them, and whether the
 * sign-in form is in the page.
 *
 * @param array<string,mixed> $args
 *
 * @return array{code:int,location:string,form:bool,body:string,cookies:list<string>}
 */
function corexAskAsVisitor(string $path, array $args = []): array
{
    $response = wp_remote_request(home_url($path), $args + ['redirection' => 0, 'timeout' => 30, 'sslverify' => false]);
    if (is_wp_error($response)) {
        throw new RuntimeException('The site did not answer ' . $path . ': ' . $response->get_error_message());
    }

    $body = wp_remote_retrieve_body($response);

    return [
        'code'     => (int) wp_remote_retrieve_response_code($response),
        'location' => (string) wp_remote_retrieve_header($response, 'location'),
        'form'     => str_contains($body, 'id="loginform"'),
        'body'     => $body,
        'cookies'  => array_map(
            static fn (WP_Http_Cookie $cookie): string => $cookie->name,
            wp_remote_retrieve_cookies($response),
        ),
    ];
}

/** A sign-in at wp-login.php, as the form posts it. */
function corexSignInAt(string $path, string $login, string $password): array
{
    return corexAskAsVisitor($path, [
        'method'  => 'POST',
        'body'    => ['log' => $login, 'pwd' => $password, 'wp-submit' => 'Log In', 'testcookie' => '1'],
        'cookies' => ['wordpress_test_cookie' => 'WP Cookie check'],
    ]);
}

/** Store the settings the running site reads on its next request. */
function corexStoreLoginSettings(array $stored): void
{
    update_option(LoginProtectionSettingsStore::OPTION, $stored, true);
}

/** The option row as the database holds it, to prove a read did not rewrite it. */
function corexStoredLoginRow(): string
{
    global $wpdb;

    return (string) $wpdb->get_var($wpdb->prepare(
        "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s",
        LoginProtectionSettingsStore::OPTION,
    ));
}

/** What the previous release's save wrote for its two keys, every other key at its default. */
function corexPreviousReleaseSave(bool $enabled, bool $hide, string $slug = 'corex-login'): array
{
    return [
        'enabled'                  => $enabled,
        'custom_slug'              => $slug,
        'block_default_endpoints'  => $hide,
        'threshold'                => 5,
        'window_seconds'           => 300,
        'lockout_seconds'          => 900,
        'trusted_proxy_mode'       => false,
        'trusted_proxy_ranges'     => [],
        'retain_days'              => 30,
        'successful_login_logging' => true,
    ];
}

/** @return array<string,array{0:bool,1:bool,2:bool}> */
function corexEverySwitchCombination(): array
{
    $combinations = [];
    foreach ([false, true] as $login) {
        foreach ([false, true] as $admin) {
            foreach ([false, true] as $limits) {
                $combinations[sprintf('login %s, admin %s, limits %s', $login ? 'hidden' : 'open', $admin ? 'hidden' : 'open', $limits ? 'on' : 'off')] = [$login, $admin, $limits];
            }
        }
    }

    return $combinations;
}

function corexLoginSwitchesTable(string $name): string
{
    return (new Migrator())->fullName($name);
}

register_shutdown_function(static function (): void {
    if ($GLOBALS['corex_login_switches_http_row'] !== null) {
        OptionRow::restore(LoginProtectionSettingsStore::OPTION, $GLOBALS['corex_login_switches_http_row']['row']);
    }
});

beforeEach(function () {
    global $wpdb;

    // These are answers of a served site. The integration job on CI boots WordPress with no web
    // server in front of it, and a site on plain permalinks answers the own address by a query
    // flag instead of the paths below; the unit tests hold the same rules for both.
    if (is_wp_error(wp_remote_head(home_url('/'), ['timeout' => 10, 'sslverify' => false]))) {
        $this->markTestSkipped('No web server answers ' . home_url('/') . '; these tests ask the served site.');
    }
    if ((string) get_option('permalink_structure') === '') {
        $this->markTestSkipped('This site uses plain permalinks; these tests ask for the own address by its path.');
    }

    $this->row = OptionRow::capture(LoginProtectionSettingsStore::OPTION);
    $GLOBALS['corex_login_switches_http_row'] = ['row' => $this->row];
    $this->store = new LoginProtectionSettingsStore();
    $this->usernames = [];
    $this->users = [];
    $this->noticesBefore = (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) FROM ' . corexLoginSwitchesTable(NotificationTable::NAME));
    $this->activityBefore = (int) $wpdb->get_var('SELECT COALESCE(MAX(id), 0) FROM ' . corexLoginSwitchesTable('activity_events'));

    $this->mail = [];
    $this->keepMail = function (mixed $short, array $atts): bool {
        $this->mail[] = $atts;

        return true;
    };
    add_filter('pre_wp_mail', $this->keepMail, PHP_INT_MAX, 2);
});

afterEach(function () {
    global $wpdb;

    if (! property_exists($this, 'row')) {
        return;
    }

    OptionRow::restore(LoginProtectionSettingsStore::OPTION, $this->row);
    $GLOBALS['corex_login_switches_http_row'] = null;

    $policy = Boot::app()->container()->make(LoginProtectionPolicy::class);
    foreach ($this->usernames as $username) {
        $wpdb->delete(corexLoginSwitchesTable(LoginAttemptTable::NAME), ['identity_hash' => $policy->identityHashOf($username)]);
    }

    foreach ($wpdb->get_col($wpdb->prepare(
        'SELECT id FROM ' . corexLoginSwitchesTable(NotificationTable::NAME) . ' WHERE id > %d AND type = %s',
        $this->noticesBefore,
        LoginLockoutNotice::TYPE,
    )) as $id) {
        $wpdb->delete(corexLoginSwitchesTable('notification_user_state'), ['notification_id' => (int) $id]);
        $wpdb->delete(corexLoginSwitchesTable(NotificationTable::NAME), ['id' => (int) $id]);
    }
    $wpdb->query($wpdb->prepare(
        'DELETE FROM ' . corexLoginSwitchesTable('activity_events') . ' WHERE id > %d AND kind = %s',
        $this->activityBefore,
        SecurityActivityRecorder::LOGIN_LOCKED,
    ));

    require_once ABSPATH . 'wp-admin/includes/user.php';
    foreach ($this->users as $userId) {
        wp_delete_user($userId);
    }

    remove_filter('pre_wp_mail', $this->keepMail, PHP_INT_MAX);
});

it('FR-013, scenario 1: with only "Hide wp-login.php" on, wp-login.php is not found, the own address signs in and /wp-admin/ redirects to it', function () {
    $this->store->save(['hide_wp_login' => true, 'hide_admin' => false, 'limit_attempts' => false, 'custom_slug' => 'team-entry']);

    $login = corexAskAsVisitor('/wp-login.php');
    $own = corexAskAsVisitor('/team-entry/');
    $admin = corexAskAsVisitor('/wp-admin/');
    $shortcut = corexAskAsVisitor('/login');

    expect($login['code'])->toBe(404)
        ->and($login['form'])->toBeFalse()
        ->and($own['code'])->toBe(200)
        ->and($own['form'])->toBeTrue()
        ->and($admin['code'])->toBe(302)
        ->and($admin['location'])->toStartWith(home_url('/team-entry/'))
        // The shortcut WordPress keeps does not hand the address out.
        ->and($shortcut['location'])->not->toContain('team-entry');
});

it('FR-013, scenario 2: with only "Hide the admin" on, /wp-admin/ is not found and wp-login.php signs in', function () {
    $this->store->save(['hide_wp_login' => false, 'hide_admin' => true, 'limit_attempts' => false, 'custom_slug' => 'team-entry']);
    $login = 'switches' . strtolower(wp_generate_password(8, false));
    $password = wp_generate_password(24);
    $userId = wp_insert_user(['user_login' => $login, 'user_email' => $login . '@example.test', 'user_pass' => $password, 'role' => 'subscriber']);
    expect($userId)->toBeInt();
    $this->users[] = $userId;

    $admin = corexAskAsVisitor('/wp-admin/');
    $form = corexAskAsVisitor('/wp-login.php');
    $signedIn = corexSignInAt('/wp-login.php', $login, $password);

    expect($admin['code'])->toBe(404)
        ->and($admin['form'])->toBeFalse()
        ->and($form['code'])->toBe(200)
        ->and($form['form'])->toBeTrue()
        ->and($signedIn['code'])->toBe(302)
        ->and($signedIn['cookies'])->toContain(LOGGED_IN_COOKIE);
});

it('FR-013, scenario 3: with only "Limit failed sign-ins" on, the sixth failed attempt at wp-login.php is refused as locked', function () {
    $this->store->save(['hide_wp_login' => false, 'hide_admin' => false, 'limit_attempts' => true, 'custom_slug' => 'team-entry']);
    $username = 'switches-nobody-' . strtolower(wp_generate_password(10, false));
    $this->usernames[] = $username;
    $locked = 'Too many failed sign-in attempts. Please wait before trying again.';

    $answers = [];
    for ($attempt = 1; $attempt <= 6; $attempt++) {
        $answers[$attempt] = corexSignInAt('/wp-login.php', $username, 'not-the-password-' . $attempt);
    }

    foreach (range(1, 5) as $attempt) {
        expect($answers[$attempt]['body'])->not->toContain($locked);
    }
    expect($answers[6]['body'])->toContain($locked)
        ->and($answers[6]['cookies'])->not->toContain(LOGGED_IN_COOKIE)
        // Nothing is hidden by the limits.
        ->and(corexAskAsVisitor('/wp-login.php')['form'])->toBeTrue()
        ->and(corexAskAsVisitor('/wp-admin/')['code'])->toBe(302)
        ->and($this->mail)->toBe([]);
});

it('FR-013: with no switch on, the own address is not served and nothing is hidden', function () {
    $this->store->save(['hide_wp_login' => false, 'hide_admin' => false, 'limit_attempts' => false, 'custom_slug' => 'team-entry']);

    expect(corexAskAsVisitor('/team-entry/')['code'])->toBe(404)
        ->and(corexAskAsVisitor('/wp-login.php')['form'])->toBeTrue()
        ->and(corexAskAsVisitor('/wp-admin/')['location'])->toStartWith(home_url('/wp-login.php'));
});

it('FR-013, scenario 5: admin-ajax.php, admin-post.php and options.php stay reachable in every combination', function (bool $login, bool $admin, bool $limits) {
    $this->store->save(['hide_wp_login' => $login, 'hide_admin' => $admin, 'limit_attempts' => $limits, 'custom_slug' => 'team-entry']);

    // What each answers with no switch on, as WordPress answers a signed-out visitor: admin-ajax.php
    // with no action 400, admin-post.php with no action 200, options.php a redirect to sign in.
    $ajax = corexAskAsVisitor('/wp-admin/admin-ajax.php');
    $post = corexAskAsVisitor('/wp-admin/admin-post.php');
    $options = corexAskAsVisitor('/wp-admin/options.php');

    expect($ajax['code'])->toBe(400)
        ->and($post['code'])->toBe(200)
        ->and($options['code'])->toBe(302)
        ->and($options['location'])->toContain('redirect_to=');
})->with(corexEverySwitchCombination());

it('SC-002, FR-014, scenario 6: a site saved by the previous release answers at wp-login.php, /wp-admin/ and its own address exactly as before, and its settings are not rewritten', function (array $stored, array $before) {
    corexStoreLoginSettings($stored);
    $row = corexStoredLoginRow();

    $home = untrailingslashit(home_url());
    $answers = [];
    foreach (['/wp-login.php', '/wp-admin/', '/corex-login/'] as $path) {
        $answer = corexAskAsVisitor($path);
        $location = str_replace([rawurlencode($home), $home], ['{home-encoded}', '{home}'], $answer['location']);
        $answers[$path] = [$answer['code'], $location, $answer['form']];
    }

    expect($answers)->toBe($before)
        ->and(corexStoredLoginRow())->toBe($row);
})->with([
    'protection on, hiding on, the address corex-login (Muva and Perego)' => [
        corexPreviousReleaseSave(true, true),
        [
            '/wp-login.php' => [404, '', false],
            '/wp-admin/'    => [404, '', false],
            '/corex-login/' => [200, '', true],
        ],
    ],
    'protection on, hiding off' => [
        corexPreviousReleaseSave(true, false),
        [
            '/wp-login.php' => [200, '', true],
            '/wp-admin/'    => [302, '{home}/corex-login/?redirect_to={home-encoded}%2Fwp-admin%2F&reauth=1', false],
            '/corex-login/' => [200, '', true],
        ],
    ],
    'protection off, hiding stored on' => [
        corexPreviousReleaseSave(false, true),
        [
            '/wp-login.php' => [200, '', true],
            '/wp-admin/'    => [302, '{home}/wp-login.php?redirect_to={home-encoded}%2Fwp-admin%2F&reauth=1', false],
            '/corex-login/' => [404, '', false],
        ],
    ],
    'protection off, hiding stored off' => [
        corexPreviousReleaseSave(false, false),
        [
            '/wp-login.php' => [200, '', true],
            '/wp-admin/'    => [302, '{home}/wp-login.php?redirect_to={home-encoded}%2Fwp-admin%2F&reauth=1', false],
            '/corex-login/' => [404, '', false],
        ],
    ],
]);
