<?php

/**
 * Unit test: how a Releases request was signed in, and whether the site lets an Application
 * Password install a release (spec 123, FR-014 and FR-014a; DECISIONS #512).
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\Releases\ReleaseCaller;

beforeEach(function () {
    Functions\when('get_current_user_id')->justReturn(7);
    $this->named = static fn (int $userId, string $uuid): string => "password $uuid of user $userId";
});

it('names the Application Password WordPress signed the request in with', function () {
    Functions\when('rest_get_authenticated_app_password')->justReturn('8a1f');

    expect((new ReleaseCaller(true, $this->named))->applicationPassword())->toBe('password 8a1f of user 7');
});

it('names none for a request signed in another way, or not at all', function (mixed $uuid) {
    Functions\when('rest_get_authenticated_app_password')->justReturn($uuid);

    expect((new ReleaseCaller(true, $this->named))->applicationPassword())->toBeNull();
})->with(['a cookie' => [null], 'an empty uuid' => ['']]);

it('takes HTTPS, or a local site, as safe to sign a release in over', function (bool $ssl, string $environment, bool $safe) {
    Functions\when('is_ssl')->justReturn($ssl);
    Functions\when('wp_get_environment_type')->justReturn($environment);

    expect((new ReleaseCaller(true))->overHttps())->toBe($safe);
})->with([
    'https, production' => [true, 'production', true],
    'http, local'       => [false, 'local', true],
    'http, staging'     => [false, 'staging', false],
    'http, production'  => [false, 'production', false],
]);

it('keeps the push off on a site whose configuration does not mention it', function () {
    // No test defines the constant.
    expect(ReleaseCaller::ofThisSite()->pushAllowed())->toBeFalse();
});
