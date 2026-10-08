<?php

/**
 * Reads a release package's description with the class a site reads it with, in a PHP process
 * that is not WordPress, and prints what it understood as JSON.
 *
 * Run by `tests/build-shared-host-dist.test.js`: the builder writes the description in Node and
 * a site reads it in PHP, so the only proof they agree is to have the one read the other's.
 *
 * Usage: php tests/Support/read-release-manifest.php <path to corex-release.json>
 */

declare(strict_types=1);

define('ABSPATH', __DIR__);

/** WordPress's translation function, as far as a refusal's wording needs it here. */
function __(string $text, string $domain = 'default'): string
{
    return $text;
}

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use Corex\Config\Releases\ReleaseManifest;
use Corex\Config\Releases\ReleaseRefused;

try {
    $manifest = ReleaseManifest::fromJson((string) file_get_contents($argv[1]));
} catch (ReleaseRefused $refused) {
    echo json_encode(['refused' => $refused->reason, 'message' => $refused->getMessage()]);
    exit(0);
}

echo json_encode([
    'version'           => $manifest->version,
    'client'            => $manifest->client,
    'requiresPhp'       => $manifest->requiresPhp,
    'requiresWordPress' => $manifest->requiresWordPress,
    'wordPressVersion'  => $manifest->wordPressVersion,
    'releasePaths'      => $manifest->releasePaths,
]);
