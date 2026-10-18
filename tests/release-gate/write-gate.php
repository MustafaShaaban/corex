<?php

/**
 * Writes the maintenance gate an installation writes (spec 107, FR-024a), into the folder given
 * and for the key given, with the page that reads it as WordPress does. What the CI job "The
 * release key gets through maintenance on LiteSpeed" serves.
 *
 *     php tests/release-gate/write-gate.php <folder> <key>
 *
 * @package Corex\Tests
 */

declare(strict_types=1);

[, $folder, $key] = $argv;

define('ABSPATH', $folder . '/');

// The gate's only translated words are its refusal, which a folder made for it does not meet.
function __(string $text): string
{
    return $text;
}

$maintenance = dirname(__DIR__, 2) . '/plugins/corex-config/src/Releases/ReleaseMaintenance.php';
require $maintenance;

(new Corex\Config\Releases\ReleaseMaintenance($folder))->begin(hash('sha256', $key), time());
copy($maintenance, $folder . '/ReleaseMaintenance.php');
copy(__DIR__ . '/probe.php', $folder . '/probe.php');
