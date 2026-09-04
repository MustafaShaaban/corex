<?php

/**
 * Base TestCase for integration tests that require a real WordPress network.
 *
 * @package Corex\Tests\Integration\Multisite
 */

declare(strict_types=1);

namespace Corex\Tests\Integration\Multisite;

use Closure;
use JsonException;
use Corex\Tests\Integration\TestCase as IntegrationTestCase;

abstract class TestCase extends IntegrationTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! function_exists('is_multisite') || ! is_multisite()) {
            self::markTestSkipped('WordPress Multisite (./wp-ms) not loaded; multisite test skipped.');
        }
    }

    protected function siteIdForPath(string $path): int
    {
        $sites = get_sites([
            'number' => 1,
            'path'   => $path,
            'fields' => 'ids',
        ]);

        self::assertCount(1, $sites, 'Expected one fixture site at ' . $path);

        return (int) $sites[0];
    }

    protected function onSite(int $siteId, Closure $callback): mixed
    {
        switch_to_blog($siteId);

        try {
            return $callback();
        } finally {
            restore_current_blog();
        }
    }

    /**
     * Boot WordPress in a separate request so add-on providers resolve for that site's activation
     * state instead of reusing the test runner's main-site Boot singleton.
     *
     * @return array<string, mixed>
     * @throws JsonException
     */
    protected function wpCliJson(int $siteId, string $php): array
    {
        $root = dirname(__DIR__, 3);
        $command = [
            'wp',
            'eval',
            $php,
            '--path=' . $root . '/wp-ms',
            '--url=' . get_site_url($siteId),
            '--skip-themes',
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, $root);

        self::assertIsResource($process, 'Could not start WP-CLI for multisite assertion.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        self::assertSame(0, $exitCode, "WP-CLI failed:\n" . $stderr);
        self::assertMatchesRegularExpression('/COREX_MS_JSON:(\{[^\r\n]+\})/', $stdout);
        preg_match('/COREX_MS_JSON:(\{[^\r\n]+\})/', $stdout, $matches);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
