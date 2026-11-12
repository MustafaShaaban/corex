<?php

/**
 * The Releases routes signed in with an Application Password (spec 123, FR-014 and FR-014a;
 * DECISIONS #512).
 *
 * WordPress signs a request in with an Application Password by setting the user and the
 * password's UUID; these tests do the same, through the REST server, with no nonce. The site
 * is one of the test's own, built with and without `COREX_ALLOW_RELEASE_PUSH` said true.
 *
 * @package Corex\Tests\Integration\Releases
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Releases\InstalledRelease;
use Corex\Config\Releases\ReleaseProgress;
use Corex\Config\Releases\ReleaseRestGateway;
use Corex\Config\Releases\ReleasesController;
use Corex\Tests\Support\ReleaseInstaller;
use Corex\Tests\Support\ReleasePackages;

/**
 * Sign the user in as WordPress does for a request carrying one of their Application Passwords.
 *
 * @return string The password's UUID.
 */
function signInWithApplicationPassword(int $userId, string $name): string
{
    [, $item] = WP_Application_Passwords::create_new_application_password($userId, ['name' => $name]);

    wp_set_current_user($userId);
    $GLOBALS['wp_rest_application_password_uuid'] = $item['uuid'];

    return $item['uuid'];
}

beforeEach(function () {
    $admins      = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin = (int) ($admins[0] ?? 0);
    $this->uuid  = signInWithApplicationPassword($this->admin, 'release push');
    ReleaseInstaller::$signedByTheScreen = false;

    // A push comes over HTTPS (FR-014). Said here, not left to the install: a `local` site would let
    // plain HTTP through and hide a refusal every other site gives, which is how this passed on a
    // local install and failed in CI.
    $this->https     = $_SERVER['HTTPS'] ?? null;
    $_SERVER['HTTPS'] = 'on';

    $this->sites = [];
    $this->site  = function (bool $pushAllowed): ReleaseInstaller {
        $site          = new ReleaseInstaller(ReleaseInstaller::RUNNING_0_44, pushAllowed: $pushAllowed);
        $this->sites[] = $site;

        Boot::app()->container()->instance(ReleasesController::class, $site->controller);
        // A server built before this has another controller on these routes.
        $GLOBALS['wp_rest_server'] = null;

        return $site;
    };

    delete_option(InstalledRelease::OPTION);
});

afterEach(function () {
    if ($this->https === null) {
        unset($_SERVER['HTTPS']);
    } else {
        $_SERVER['HTTPS'] = $this->https;
    }
    ReleaseInstaller::$signedByTheScreen = true;
    unset($GLOBALS['wp_rest_application_password_uuid']);
    WP_Application_Passwords::delete_application_password($this->admin, $this->uuid);
    Boot::app()->container()->singleton(ReleasesController::class);
    $GLOBALS['wp_rest_server'] = null;

    foreach ($this->sites as $site) {
        $site->copies->discard();
        $site->remove();
    }
    delete_option(InstalledRelease::OPTION);
});

it('installs a release from start to finish, and the history names the Application Password', function () {
    $site = ($this->site)(true);

    $prepared = $site->installTheRelease();

    expect($site->installed->version())->toBe('0.45.0')
        ->and($site->maintenance->isOn())->toBeFalse();

    $logged = $site->store->log();
    expect(end($logged))->toMatchArray([
        'event'          => 'installed',
        'version'        => '0.45.0',
        'by'             => $this->admin,
        'signed_in_with' => 'release push',
    ]);

    $events = $site->activity->events;
    expect($events[0]->kind)->toBe('release.installed')
        ->and($events[0]->context['signed_in_with'])->toBe('release push')
        ->and(wp_json_encode([$events[0]->context, $logged]))->not->toContain($prepared['key']);
});

it('receives a package in parts', function () {
    ($this->site)(true);
    $package = (string) file_get_contents(ReleasePackages::package());
    $hash    = hash('sha256', $package);
    $name    = 'corex-release-acme-0.44.0-20261008-180000.zip';

    $half = intdiv(strlen($package), 2);
    // A part is the request's body, which `ask()` does not send: built here as the screen sends it.
    $append = static function (int $offset, string $part) use ($hash): WP_REST_Response {
        $request = new WP_REST_Request('POST', "/corex/v1/releases/uploads/$hash");
        $request->set_query_params(['offset' => $offset]);
        $request->set_body($part);

        return rest_get_server()->dispatch($request);
    };

    $first  = $append(0, substr($package, 0, $half));
    $held   = ReleaseInstaller::ask('GET', "/releases/uploads/$hash");
    $second = $append($half, substr($package, $half));
    $kept   = ReleaseInstaller::ask('POST', "/releases/uploads/$hash/complete", ['size' => strlen($package), 'name' => $name]);

    expect($first->get_data()['data']['received'])->toBe($half)
        ->and($held->get_data()['data']['received'])->toBe($half)
        ->and($second->get_data()['data']['received'])->toBe(strlen($package))
        ->and($kept->get_data())->toMatchArray([ReleaseRestGateway::MARK => 1, 'ok' => true, 'data' => ['package' => $name]]);
});

it('refuses an Application Password on every route of a site that does not allow the push', function (string $method, string $route) {
    ($this->site)(false);

    $response = ReleaseInstaller::ask($method, $route);

    expect($response->get_status())->toBe(403)
        ->and($response->get_data())->toMatchArray([ReleaseRestGateway::MARK => 1, 'ok' => false, 'reason' => ReleaseRestGateway::PUSH_OFF])
        ->and($response->get_data()['message'])->toContain("define( 'COREX_ALLOW_RELEASE_PUSH', true );");
})->with([
    'overview'         => ['GET', '/releases'],
    'received'         => ['GET', '/releases/uploads/' . str_repeat('a', 64)],
    'inspect'          => ['POST', '/releases/inspect'],
    'prepare'          => ['POST', '/releases/install/prepare'],
    'confirm'          => ['POST', '/releases/install/confirm'],
    'step'             => ['POST', '/releases/install/step'],
    'finish'           => ['POST', '/releases/install/finish'],
    'cancel'           => ['POST', '/releases/install/cancel'],
    'return'           => ['POST', '/releases/return'],
    'leftovers/remove' => ['POST', '/releases/leftovers/remove'],
]);

it('refuses a request with neither a nonce nor an Application Password, whatever the site allows', function () {
    ($this->site)(true);
    unset($GLOBALS['wp_rest_application_password_uuid']);

    $response = ReleaseInstaller::ask('GET', '/releases');

    expect($response->get_status())->toBe(403)
        ->and($response->get_data()['reason'])->toBe(ReleaseRestGateway::NOT_SIGNED);
});

it('keeps the screen as it was: its nonce signs a request on a site that does not allow the push', function () {
    $site = ($this->site)(false);
    unset($GLOBALS['wp_rest_application_password_uuid']);
    ReleaseInstaller::$signedByTheScreen = true;

    $site->installTheRelease();

    $logged = $site->store->log();
    expect($site->installed->version())->toBe('0.45.0')
        ->and(end($logged))->not->toHaveKey('signed_in_with');
});

it('refuses an Application Password over plain HTTP on a site that is not a local one', function () {
    if (wp_get_environment_type() === 'local') {
        $this->markTestSkipped('A local site lets an Application Password through over plain HTTP.');
    }
    ($this->site)(true);
    unset($_SERVER['HTTPS']);

    $response = ReleaseInstaller::ask('POST', '/releases/install/step');

    expect($response->get_status())->toBe(403)
        ->and($response->get_data()['reason'])->toBe(ReleaseRestGateway::PUSH_NEEDS_HTTPS);
});

it('is closed to an account that may not install releases, whatever its Application Password', function () {
    ($this->site)(true);
    $inserted = wp_insert_user(['user_login' => 'release-push-editor-' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor']);
    // A failed insert is a WP_Error, which (int) turns into 1: the clean-up below would then delete
    // the install's first administrator.
    expect($inserted)->toBeInt();
    $editor = $inserted;
    signInWithApplicationPassword($editor, 'editor push');

    try {
        $status = ReleaseInstaller::ask('POST', '/releases/install/step')->get_status();
    } finally {
        // WordPress loads this for an admin page and for nothing else.
        require_once ABSPATH . 'wp-admin/includes/user.php';
        wp_delete_user($editor);
    }

    expect($status)->toBe(403);
});
