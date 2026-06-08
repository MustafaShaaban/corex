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

if (is_file($wpLoad)) {
    require_once $wpLoad;
}
