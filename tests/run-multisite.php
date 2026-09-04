<?php

/**
 * Optional local entry point for the real-network integration suite.
 *
 * @package Corex\Tests
 */

declare(strict_types=1);

$root = dirname(__DIR__);
$wpLoad = $root . '/wp-ms/wp-load.php';

if (! is_file($wpLoad)) {
    fwrite(STDOUT, "Corex multisite integration tests skipped: ./wp-ms was not found.\n");
    fwrite(STDOUT, "Create it with: powershell -File .\\scripts\\setup-wordpress.ps1 -Multisite\n");
    exit(0);
}

$pest = $root . '/vendor/pestphp/pest/bin/pest';
$command = [PHP_BINARY, $pest, '--configuration=' . $root . '/phpunit-multisite.xml.dist'];
$descriptors = [
    0 => STDIN,
    1 => STDOUT,
    2 => STDERR,
];
$process = proc_open($command, $descriptors, $pipes, $root);

if (! is_resource($process)) {
    fwrite(STDERR, "Could not start the Corex multisite integration suite.\n");
    exit(1);
}

exit(proc_close($process));
