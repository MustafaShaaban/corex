<?php

/**
 * The `.env` line each update guide gives, run through the real `.env` source and the real update
 * service (spec 034 FR-005).
 *
 * UpdateServiceTest hands the service a config double that answers `updates.endpoint` whatever a
 * site wrote, so no test said which `.env` name reaches it. The three guides gave a name nothing
 * reads, and the check of a site with no update source is a silent no-op (2026-10-13,
 * DECISIONS #363).
 *
 * @package Corex\Tests\Unit\Update
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Support\BootLogger;
use Corex\Support\Config\Repository;
use Corex\Support\Config\Sources\DotenvSource;
use Corex\Update\UpdateChecker;
use Corex\Update\UpdateService;

it('fetches the manifest from the address a guide\'s .env line gives', function (string $guide) {
    $text = (string) file_get_contents(dirname(__DIR__, 3) . '/' . $guide);

    expect(preg_match('/^([A-Z_]+)=(https:\/\/\S+\/manifest\.json)$/m', $text, $line))->toBe(1);

    $directory = sys_get_temp_dir() . '/corex_update_guide_' . uniqid('', true);
    mkdir($directory);
    file_put_contents($directory . '/.env', $line[0] . "\n");

    $asked = [];
    Functions\when('is_wp_error')->justReturn(false);
    Functions\when('wp_remote_retrieve_body')->justReturn('{}');
    Functions\when('wp_remote_get')->alias(function (string $url) use (&$asked): array {
        $asked[] = $url;

        return [];
    });

    try {
        $config = new Repository([new DotenvSource($directory, new BootLogger(debug: false))]);

        (new UpdateService(new UpdateChecker(), 'corex-core/corex-core.php', 'corex-core', '0.20.0', $config))
            ->fetchManifest();
    } finally {
        unlink($directory . '/.env');
        rmdir($directory);
    }

    expect($asked)->toBe([$line[2]]);
})->with([
    'docs/en/05-deployment/updates-and-distribution.md',
    'docs-app/src/content/docs/guides/updates.md',
    'plugins/corex-core/README.md',
]);
