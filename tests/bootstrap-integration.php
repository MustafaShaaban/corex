<?php

/**
 * Integration bootstrap: load the real WordPress install at ./wp so the framework
 * boots in a genuine runtime. WordPress defines ABSPATH itself (to ./wp), so — unlike
 * the unit bootstrap — we must NOT predefine it.
 *
 * @package Corex\Tests
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/vendor/autoload.php';

$wpLoad = dirname(__DIR__) . '/wp/wp-load.php';

// Fail loudly rather than proceeding without the runtime under test. Skipping the require left the
// suite running against no WordPress at all: tests that call a core function died with an undefined
// function deep in a test body, and any test that happened not to call one could still pass — a
// green integration run proving nothing. `wp/` is not tracked in git, so this is the state of a
// fresh clone and of CI, not a hypothetical.
if (! is_file($wpLoad)) {
    fwrite(STDERR, <<<'MESSAGE'

    Corex integration tests need a real WordPress install at ./wp, and none was found.

    These tests boot the framework inside WordPress on purpose — they are not unit tests with
    WordPress mocked out, so there is nothing meaningful to run without it.

      - Locally: point ./wp at your WordPress install (the WAMP setup symlinks plugins into
        wp/wp-content/plugins).
      - For unit tests, which need no WordPress: vendor/bin/pest

    MESSAGE);

    exit(1);
}

require_once $wpLoad;
