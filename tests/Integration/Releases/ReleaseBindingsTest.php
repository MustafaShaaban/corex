<?php

/**
 * The release installer as the site itself builds it (spec 107, slice 4).
 *
 * The other tests of the installer build it by hand over a site in the temp folder. This asks
 * the container for it, as a request does: a binding that is missing or wrong is a fatal error
 * on the Releases screen and on nothing a test of the classes would notice.
 *
 * @package Corex\Tests\Integration\Releases
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Releases\ReleaseCompletion;
use Corex\Config\Releases\ReleaseInstallation;
use Corex\Config\Releases\ReleasesController;

it('builds the controller a request is answered by, with every step behind it', function (string $class) {
    expect(Boot::app()->container()->make($class))->toBeInstanceOf($class);
})->with([ReleasesController::class, ReleaseInstallation::class, ReleaseCompletion::class]);

it('serves every route of an installation', function () {
    $routes = array_keys(rest_get_server()->get_routes());

    expect($routes)->toContain(
        '/corex/v1/releases/install/prepare',
        '/corex/v1/releases/install/confirm',
        '/corex/v1/releases/install/step',
        '/corex/v1/releases/install/finish',
        '/corex/v1/releases/install/cancel',
        '/corex/v1/releases/leftovers/remove',
    );
});
