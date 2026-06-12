<?php

/**
 * Unit tests for the update service's transient injection + fail-safe (spec 034 US1: FR-002).
 * The WP HTTP functions are stubbed so the manifest fetch + injection run headlessly.
 *
 * @package Corex\Tests\Unit\Update
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Support\Config\ConfigInterface;
use Corex\Update\UpdateChecker;
use Corex\Update\UpdateService;

function configWithEndpoint(string $endpoint): ConfigInterface
{
    return new class($endpoint) implements ConfigInterface {
        public function __construct(private string $endpoint)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'updates.endpoint' ? $this->endpoint : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'updates.endpoint';
        }
    };
}

function service(string $endpoint): UpdateService
{
    return new UpdateService(new UpdateChecker(), 'corex-core/corex-core.php', 'corex-core', '0.20.0', configWithEndpoint($endpoint));
}

function stubManifest(?array $manifest): void
{
    Functions\when('is_wp_error')->justReturn(false);
    Functions\when('wp_remote_get')->justReturn([]);
    Functions\when('wp_remote_retrieve_body')->justReturn($manifest === null ? 'not-json' : json_encode($manifest));
}

it('injects an update into the transient when a newer version is published', function () {
    stubManifest(['version' => '0.21.0', 'package' => 'https://x/corex.zip']);

    $transient = (object) ['response' => []];
    $result = service('https://updates.corex.dev/manifest.json')->checkUpdates($transient);

    expect($result->response)->toHaveKey('corex-core/corex-core.php')
        ->and($result->response['corex-core/corex-core.php']->new_version)->toBe('0.21.0');
});

it('leaves the transient untouched when current is the latest (fail-safe)', function () {
    stubManifest(['version' => '0.20.0']);

    $transient = (object) ['response' => []];

    expect(service('https://updates.corex.dev/manifest.json')->checkUpdates($transient)->response)->toBe([]);
});

it('is a safe no-op when the endpoint is empty (no source configured)', function () {
    $transient = (object) ['response' => []];

    expect(service('')->checkUpdates($transient)->response)->toBe([]);
});

it('is a safe no-op when the manifest is malformed JSON', function () {
    stubManifest(null); // body is not JSON

    $transient = (object) ['response' => []];

    expect(service('https://updates.corex.dev/manifest.json')->checkUpdates($transient)->response)->toBe([]);
});

it('builds a WP update object with the plugin file and package', function () {
    $obj = service('')->toUpdateObject([
        'new_version' => '0.21.0',
        'package'     => 'https://x/corex.zip',
        'url'         => 'https://corex.dev',
        'requires'    => '7.0',
        'tested'      => '7.0',
    ]);

    expect($obj->plugin)->toBe('corex-core/corex-core.php')
        ->and($obj->package)->toBe('https://x/corex.zip');
});
