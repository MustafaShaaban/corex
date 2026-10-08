<?php

/**
 * The Releases routes on real WordPress (spec 107, slice 3; T034).
 *
 * Asked through the REST server, as the screen asks: who may, what a refusal looks like, a
 * package received in parts and then read. The store is a folder in the temp directory, so no
 * test leaves a package in the site's own `wp-content`.
 *
 * @package Corex\Tests\Integration\Releases
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Releases\InstalledRelease;
use Corex\Config\Releases\ReleaseDesk;
use Corex\Config\Releases\ReleaseHostFacts;
use Corex\Config\Releases\ReleasePackageInspector;
use Corex\Config\Releases\ReleaseRestGateway;
use Corex\Config\Releases\ReleasesController;
use Corex\Config\Releases\ReleaseStore;
use Corex\Config\Releases\ReleaseUpload;
use Corex\Tests\Support\ReleasePackages;

/**
 * @param array<string,mixed> $params
 */
function releasesRequest(string $method, string $route, array $params = [], string $body = '', ?string $nonce = null): WP_REST_Response
{
    // A request built here is not parsed from a URL: what follows `?` is given as its query.
    [$path, $queryString] = array_pad(explode('?', $route, 2), 2, '');
    wp_parse_str($queryString, $query);

    $request = new WP_REST_Request($method, '/corex/v1' . $path);
    $request->set_header('X-WP-Nonce', $nonce ?? wp_create_nonce('wp_rest'));
    $request->set_query_params($method === 'GET' ? $params : $query);
    if ($method !== 'GET') {
        $request->set_body_params($params);
    }
    $request->set_body($body);

    return rest_get_server()->dispatch($request);
}

beforeEach(function () {
    $admins = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($admins[0] ?? 0));

    $this->content = sys_get_temp_dir() . '/corex-content-' . bin2hex(random_bytes(4));
    mkdir($this->content);
    $this->site = sys_get_temp_dir() . '/corex-site-' . bin2hex(random_bytes(4));
    mkdir($this->site);
    file_put_contents($this->site . '/corex-release.json', '{"corex_version":"0.43.5","client":"acme"}');

    // The controller the site would build, over a store and a site of the test's own.
    $store     = new ReleaseStore($this->content);
    $installed = new InstalledRelease($this->site);
    $inspector = new ReleasePackageInspector($installed, PHP_VERSION, '7.1.3');

    $this->store = $store;
    // Given to the container in place of the site's own, so the provider registers these
    // routes exactly as it does for a site, on the server's own hook, with this controller.
    $container = Boot::app()->container();
    $container->instance(ReleasesController::class, new ReleasesController(
        $container->make(ReleaseRestGateway::class),
        new ReleaseDesk($store, $inspector, $installed, [$this->content]),
        new ReleaseUpload($store),
    ));
    // A server built before this test has the site's own controller on these routes.
    $GLOBALS['wp_rest_server'] = null;
    delete_option(InstalledRelease::OPTION);
});

afterEach(function () {
    Boot::app()->container()->singleton(ReleasesController::class);
    $GLOBALS['wp_rest_server'] = null;
    ReleasePackages::clean();

    $remove = static function (string $path) use (&$remove): void {
        foreach (array_diff(scandir($path) ?: [], ['.', '..']) as $entry) {
            is_dir("$path/$entry") ? $remove("$path/$entry") : unlink("$path/$entry");
        }
        rmdir($path);
    };
    $remove($this->content);
    $remove($this->site);
});

it('is closed to somebody who may not install releases', function () {
    $editor = wp_insert_user(['user_login' => 'release-editor-' . wp_rand(), 'user_pass' => wp_generate_password(), 'role' => 'editor']);
    wp_set_current_user((int) $editor);

    try {
        $status = releasesRequest('GET', '/releases')->get_status();
    } finally {
        wp_delete_user((int) $editor);
    }

    expect($status)->toBe(403);
});

it('refuses a request this screen did not sign, with an answer the screen can read', function () {
    $response = releasesRequest('GET', '/releases', nonce: 'not-a-nonce');

    expect($response->get_status())->toBe(403)
        ->and($response->get_data())->toMatchArray([
            ReleaseRestGateway::MARK => 1,
            'ok'                     => false,
            'reason'                 => ReleaseRestGateway::NOT_SIGNED,
        ]);
});

it('says what is installed, what is in the way, and which packages are here', function () {
    copy(ReleasePackages::package(), $this->store->fileIn('incoming', 'corex-release-acme-0.44.0-20261008-180000.zip'));

    $answer = releasesRequest('GET', '/releases')->get_data();

    expect($answer[ReleaseRestGateway::MARK])->toBe(1)
        ->and($answer['ok'])->toBeTrue()
        ->and($answer['data']['installed'])->toBe(['known' => true, 'version' => '0.43.5', 'client' => 'acme'])
        ->and($answer['data']['blockers'])->toBe([])
        ->and(array_column($answer['data']['packages'], 'name'))->toBe(['corex-release-acme-0.44.0-20261008-180000.zip'])
        ->and($answer['data']['part_bytes'])->toBeGreaterThanOrEqual(256 * 1024);
});

it('receives a package in parts, keeps it, and says what it is', function () {
    $package = (string) file_get_contents(ReleasePackages::package());
    $hash    = hash('sha256', $package);
    $name    = 'corex-release-acme-0.44.0-20261008-180000.zip';
    $half    = intdiv(strlen($package), 2);

    $first = releasesRequest('POST', "/releases/uploads/$hash?offset=0", body: substr($package, 0, $half));
    // The second half, sent from the wrong place: the answer says where to send it from.
    $outOfStep = releasesRequest('POST', "/releases/uploads/$hash?offset=0", body: substr($package, $half));
    $held      = releasesRequest('GET', "/releases/uploads/$hash");
    $second    = releasesRequest('POST', "/releases/uploads/$hash?offset=$half", body: substr($package, $half));
    $kept      = releasesRequest('POST', "/releases/uploads/$hash/complete", ['size' => strlen($package), 'name' => $name]);
    $statement = releasesRequest('POST', '/releases/inspect', ['package' => $name]);

    expect($first->get_data()['data']['received'])->toBe($half)
        ->and($outOfStep->get_status())->toBe(409)
        ->and($outOfStep->get_data())->toMatchArray([ReleaseRestGateway::MARK => 1, 'ok' => false, 'received' => $half])
        ->and($held->get_data()['data']['received'])->toBe($half)
        ->and($second->get_data()['data']['received'])->toBe(strlen($package))
        ->and($kept->get_data()['data']['package'])->toBe($name)
        ->and($statement->get_data()['data'])->toMatchArray([
            'package'            => $name,
            'version'            => '0.44.0',
            'client'             => 'acme',
            'replaces'           => '0.43.5',
            'older'              => false,
            'client_unconfirmed' => false,
            'files'              => 45,
            'bytes'              => 4340,
            'blockers'           => [],
        ]);
});

it('refuses a wrong package in words, writes down that it did, and does not keep it', function () {
    $name = 'corex-release-globex-0.44.0-20261008-180000.zip';
    copy(ReleasePackages::package(['client' => 'globex']), $this->store->fileIn('incoming', $name));

    $response = releasesRequest('POST', '/releases/inspect', ['package' => $name]);
    $logged   = $this->store->log();

    expect($response->get_status())->toBe(422)
        ->and($response->get_data())->toMatchArray([ReleaseRestGateway::MARK => 1, 'ok' => false, 'reason' => 'other_client'])
        ->and($response->get_data()['message'])->toContain('globex')
        ->and($response->get_data()['message'])->toContain('removed from the site')
        ->and($logged)->toHaveCount(1)
        ->and($logged[0])->toMatchArray(['event' => 'refused', 'package' => $name, 'reason' => 'other_client', 'by' => get_current_user_id()])
        // FR-003: a package the site will not install is not left on it.
        ->and(releasesRequest('GET', '/releases')->get_data()['data']['packages'])->toBe([]);
});

it('opens where the site cannot make its folder, and says so in words when a package is sent', function () {
    // The browser job's site in CI is such a host. Its Releases route answered with PHP's own
    // error page, which the screen read as "wait and ask again" until it gave up (PR #324).
    // Here the folder's name is taken by a file, which no system makes a folder of.
    file_put_contents($this->content . '/corex-releases', '');

    $overview = releasesRequest('GET', '/releases');
    $sending  = releasesRequest('GET', '/releases/uploads/' . str_repeat('a', 64));

    expect($overview->get_status())->toBe(200)
        ->and($overview->get_data())->toMatchArray([ReleaseRestGateway::MARK => 1, 'ok' => true])
        ->and($overview->get_data()['data']['packages'])->toBe([])
        ->and($sending->get_status())->toBe(422)
        ->and($sending->get_data())->toMatchArray([ReleaseRestGateway::MARK => 1, 'ok' => false, 'reason' => ReleaseHostFacts::NOT_WRITABLE])
        ->and($sending->get_data()['message'])->toContain('corex-releases');
});

it('will not be asked about a file by a path', function (string $package) {
    $response = releasesRequest('POST', '/releases/inspect', ['package' => $package]);

    expect($response->get_status())->toBe(422)
        ->and($response->get_data()['ok'])->toBeFalse();
})->with([
    'the site\'s configuration' => '../../../wp-config.php',
    'a package that is not here' => 'corex-release-acme-9.9.9-20270101-000000.zip',
    'nothing'                   => '',
]);
