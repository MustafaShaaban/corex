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

require_once $wpLoad;
