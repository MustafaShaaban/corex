<?php

/**
 * Base TestCase for integration tests that require a real WordPress network.
 *
 * @package Corex\Tests\Multisite
 */

declare(strict_types=1);

namespace Corex\Tests\Multisite;

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

    /**
     * The WP-CLI executable proc_open() can actually launch here.
     *
     * Composer installs two shims side by side: an extensionless `wp` shell script and a `wp.bat`.
     * proc_open()'s array form goes straight to CreateProcess on Windows, which runs neither shell
     * scripts nor .bat files found by PATH lookup — so `['wp', ...]` simply returns false, and the
     * whole suite failed here with "Could not start WP-CLI" on the platform this project is
     * developed on. On Linux the reverse holds and only `wp` exists. Probe once, cache the answer.
     */
    private static function wpCliBinary(): string
    {
        static $binary = null;

        if ($binary !== null) {
            return $binary;
        }

        // Most-likely-first, so the probe normally succeeds on its first try. A failed proc_open()
        // raises "CreateProcess failed, error code: 2", and PHPUnit promotes that to a warning that
        // marks the test even though `@` suppressed the message — so the handler is swapped out for
        // the duration rather than relying on the silence operator.
        $candidates = PHP_OS_FAMILY === 'Windows' ? ['wp.bat', 'wp'] : ['wp', 'wp.bat'];

        foreach ($candidates as $candidate) {
            set_error_handler(static fn (): bool => true);

            try {
                $probe = proc_open(
                    [$candidate, '--version'],
                    [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
                    $pipes,
                );
            } finally {
                restore_error_handler();
            }

            if (! is_resource($probe)) {
                continue;
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($probe);

            return $binary = $candidate;
        }

        // Nothing ran. Return the POSIX name so the assertion message names something real.
        return $binary = 'wp';
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
        $root = dirname(__DIR__, 2);
        // Forward slashes, deliberately. Composer's wp.bat shim ends in `sh "%BIN_TARGET%" %*`, so
        // every argument is re-parsed by a POSIX shell — where the backslashes dirname() returns on
        // Windows are escape characters. `--path=C:\wamp64\www\corex/wp-ms` arrives as
        // `C:wamp64wwwcorex/wp-ms`, WP-CLI cannot see a WordPress there, falls back to the working
        // directory and reports "This does not seem to be a WordPress installation" against a path
        // nobody passed. PHP and WP-CLI both accept forward slashes on Windows.
        $rootPath = str_replace('\\', '/', $root);

        // `eval-file`, never `eval`. The snippet cannot travel as an argv element: Composer's
        // wp.bat shim ends in `sh "%BIN_TARGET%" %*`, so on Windows every argument is re-parsed by
        // a POSIX shell. That shell expands `$container`, eats the backslashes in `\\\\Corex\\\\Boot`
        // and splits the snippet on its newlines — after which `--path` is no longer where WP-CLI
        // expects it, and the failure reads "This does not seem to be a WordPress installation"
        // against the working directory rather than the path actually passed. A file has no
        // quoting to get wrong, on either platform.
        $scriptPath = tempnam(sys_get_temp_dir(), 'corex-ms-') . '.php';
        file_put_contents($scriptPath, '<?php ' . $php);

        $command = [
            self::wpCliBinary(),
            'eval-file',
            str_replace('\\', '/', $scriptPath),
            '--path=' . $rootPath . '/wp-ms',
            '--url=' . get_site_url($siteId),
            '--skip-themes',
        ];
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptors, $pipes, $root);

        self::assertIsResource(
            $process,
            'Could not start WP-CLI (' . self::wpCliBinary() . ') for the multisite assertion.',
        );
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        @unlink($scriptPath);

        self::assertSame(0, $exitCode, "WP-CLI failed:\n" . $stderr);
        self::assertMatchesRegularExpression('/COREX_MS_JSON:(\{[^\r\n]+\})/', $stdout);
        preg_match('/COREX_MS_JSON:(\{[^\r\n]+\})/', $stdout, $matches);

        return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
    }
}
