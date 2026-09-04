<?php

/**
 * Multisite integration bootstrap: load the real WordPress network at ./wp-ms.
 * WordPress defines ABSPATH itself, so it must not be predefined here.
 *
 * @package Corex\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$wpLoad = dirname(__DIR__) . '/wp-ms/wp-load.php';

if (! is_file($wpLoad)) {
    fwrite(STDERR, <<<'MESSAGE'

    Corex multisite integration tests need a real WordPress network at ./wp-ms, and none was found.

    These tests boot Corex inside WordPress Multisite on purpose. Create the network before running
    this bootstrap:

      - Locally: powershell -File .\scripts\setup-wordpress.ps1 -Multisite
      - In CI: use .github/actions/provision-wordpress with multisite: 'true', path: wp-ms,
        and db-prefix: cxms_

    For headless unit tests, which need no WordPress: vendor/bin/pest

    MESSAGE);

    exit(1);
}

// WordPress logs new-site registrations through wpmu_log_new_registrations(), which reads
// $_SERVER['REMOTE_ADDR'] unconditionally (wp-includes/ms-functions.php). There is no remote
// address under CLI, so creating a site raises "preg_replace(): Passing null to parameter #3" from
// core rather than from anything here. A loopback address is what a request would carry and is what
// the site-creation tests need in order to report on their own behaviour instead of on core's.
$_SERVER['REMOTE_ADDR'] ??= '127.0.0.1';

require_once $wpLoad;

// Set from the network itself rather than guessed. Site creation and deletion run core paths that
// read $_SERVER['HTTP_HOST'] (get_site_by_path() and friends), which CLI does not provide — so
// deleting a site raised `Undefined array key "HTTP_HOST"` from core and marked the test warned.
// The network knows its own domain, and it differs between here and CI, so ask it.
if (! isset($_SERVER['HTTP_HOST']) && function_exists('get_network')) {
    $network = get_network();

    if ($network !== null) {
        $_SERVER['HTTP_HOST'] = $network->domain;
    }
}
