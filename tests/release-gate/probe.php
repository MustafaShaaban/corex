<?php

/**
 * A page that reads the maintenance gate as WordPress does (`wp_is_maintenance_mode()`: included
 * inside a function, `$upgrading` global, held off while it is less than ten minutes old), and
 * says what the web server handed PHP. Served by the CI job "The release key gets through
 * maintenance on LiteSpeed" from the folder `write-gate.php` wrote.
 *
 * @package Corex\Tests
 */

declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

require __DIR__ . '/ReleaseMaintenance.php';

$upgrading = (static function (): float|int {
    global $upgrading;

    require ABSPATH . '.maintenance';

    return $upgrading;
})();

header('Content-Type: application/json');

echo json_encode([
    'held_off'       => (time() - $upgrading) < 600,
    'key_from'       => Corex\Config\Releases\ReleaseMaintenance::keyCameFrom(),
    'php_was_handed' => [
        'the_key_header' => isset($_SERVER['HTTP_X_COREX_RELEASE_KEY']),
        'authorization'  => isset($_SERVER['HTTP_AUTHORIZATION']),
        'getallheaders'  => function_exists('getallheaders'),
    ],
    'server'         => $_SERVER['SERVER_SOFTWARE'] ?? '',
    'sapi'           => PHP_SAPI,
], JSON_PRETTY_PRINT), "\n";
