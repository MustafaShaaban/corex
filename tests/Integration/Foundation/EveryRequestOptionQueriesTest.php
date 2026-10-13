<?php

/**
 * Integration test: the three options of CoreX's own stores that every request reads are not asked
 * of the database one at a time, on a site that stored none of them and on a site that stored each
 * under an older release (DECISIONS #366).
 *
 * Each was saved with autoload off and read on every request, so each was a query whether its row
 * was there or not. A cron or an admin request now turns a stored row over and writes the row of
 * an option that has none; from then on WordPress loads all three with the site's options.
 *
 * The test names the options it forbids and looks for nothing else, so a query somebody adds for
 * another reason does not fail it. Each request runs in a process of its own, because this one
 * booted long ago and its queries were not kept.
 *
 * @package Corex\Tests\Integration\Foundation
 */

declare(strict_types=1);

use Corex\Tests\Support\OptionRow;

/**
 * The options, by who reads them on every request.
 */
const COREX_OPTIONS_EVERY_REQUEST_READS = [
    // LoginProtectionSettingsStore, as the login guard is made while CoreX boots.
    'corex_login_protection_settings',
    // OperationsModeStore, for the maintenance and coming-soon guards on a front-end page.
    'corex_operations_mode',
    // The email add-on, on `init`, to install the Email Studio defaults a release added.
    'corex_email_studio_defaults_version',
];

/**
 * Run one request in a process of its own and report which of the named options it asked the
 * database for one at a time, and what it read from them.
 *
 * @param 'front page'|'cron' $kind A cron request loads WordPress and renders nothing.
 *
 * @return array{template_redirect: bool, queries_heard: int, asked_one_at_a_time: list<string>, login: array{enabled: bool, slug: string}, mode: array{current: string, declared: bool}}
 */
function everyRequestOptionsReadBy(string $kind): array
{
    $script = tempnam(sys_get_temp_dir(), 'corex-request-') . '.php';
    file_put_contents($script, sprintf(
        <<<'PHP'
        <?php
        // Before WordPress loads, so that every query of the request is kept.
        define('SAVEQUERIES', true);
        // The request under test is this one alone: it starts no cron run on the install.
        defined('DISABLE_WP_CRON') || define('DISABLE_WP_CRON', true);

        $_SERVER['HTTP_HOST'] = %s;
        $_SERVER['SERVER_NAME'] = %s;
        $_SERVER['REQUEST_URI'] = '/';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';

        // Registered before WordPress registers its own, so it reports first, and reports a request
        // that a guard ended as well as one that rendered.
        register_shutdown_function(static function (): void {
            while (ob_get_level() > 0) {
                ob_end_clean();
            }

            global $wpdb;
            $named = %s;
            $asked = [];

            foreach ((array) $wpdb->queries as $query) {
                if (preg_match('/WHERE option_name = \'([^\']+)\' LIMIT 1$/', trim($query[0]), $found) === 1) {
                    $asked[] = $found[1];
                }
            }

            $container = \Corex\Boot::app()->container();
            $login = $container->make(\Corex\Config\Security\LoginProtection\LoginProtectionSettingsStore::class)->current();
            $mode = $container->make(\Corex\Config\Operations\OperationsModeStore::class);

            echo 'COREX_REQUEST_JSON:' . wp_json_encode([
                'template_redirect' => did_action('template_redirect') > 0,
                'queries_heard' => count((array) $wpdb->queries),
                'asked_one_at_a_time' => array_values(array_intersect($named, $asked)),
                'login' => ['enabled' => $login->enabled, 'slug' => $login->customSlug],
                'mode' => ['current' => $mode->current(), 'declared' => $mode->isDeclared()],
            ]);
        });

        ob_start();

        if (%s) {
            define('DOING_CRON', true);
            require %s;

            return;
        }

        define('WP_USE_THEMES', true);
        require %s;
        PHP,
        var_export((string) wp_parse_url(home_url(), PHP_URL_HOST) . (wp_parse_url(home_url(), PHP_URL_PORT) ? ':' . wp_parse_url(home_url(), PHP_URL_PORT) : ''), true),
        var_export((string) wp_parse_url(home_url(), PHP_URL_HOST), true),
        var_export(COREX_OPTIONS_EVERY_REQUEST_READS, true),
        $kind === 'cron' ? 'true' : 'false',
        var_export(ABSPATH . 'wp-load.php', true),
        var_export(ABSPATH . 'wp-blog-header.php', true),
    ));

    try {
        $process = proc_open(
            [PHP_BINARY, $script],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        $stdout = (string) stream_get_contents($pipes[1]);
        $stderr = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
    } finally {
        unlink($script);
    }

    expect($exitCode)->toBe(0, "The request did not finish:\n" . $stdout . $stderr);
    expect(preg_match('/COREX_REQUEST_JSON:(\{.+\})/', $stdout, $matches))->toBe(1, $stdout . $stderr);

    return json_decode($matches[1], true, flags: JSON_THROW_ON_ERROR);
}

beforeEach(function () {
    if (! class_exists(\Corex\Email\MailServiceProvider::class)) {
        $this->markTestSkipped('The email add-on, which reads the third option, is not active on this install.');
    }

    $this->rows = [];
    foreach (COREX_OPTIONS_EVERY_REQUEST_READS as $option) {
        $this->rows[$option] = OptionRow::capture($option);
    }
});

afterEach(function () {
    foreach ($this->rows ?? [] as $option => $row) {
        OptionRow::restore($option, $row);
    }
});

it('asks for none of them on a site that stored nothing, once a cron request has run', function () {
    delete_option('corex_login_protection_settings');
    delete_option('corex_operations_mode');
    // Left stored: without it the email add-on installs its defaults, and stores it, on `init`.
    OptionRow::storeUnloaded('corex_email_studio_defaults_version', $this->rows['corex_email_studio_defaults_version']['value'] ?? '3');

    $neverSaved = everyRequestOptionsReadBy('front page');
    everyRequestOptionsReadBy('cron');
    $frontPage = everyRequestOptionsReadBy('front page');

    expect($frontPage['template_redirect'])->toBeTrue()
        // The probe is not deaf: before the cron request it heard all three.
        ->and($neverSaved['asked_one_at_a_time'])->toBe(COREX_OPTIONS_EVERY_REQUEST_READS)
        ->and($frontPage['queries_heard'])->toBeGreaterThan(0)
        ->and($frontPage['asked_one_at_a_time'])->toBe([])
        // And the site is what it was: no login protection, no declared mode.
        ->and($frontPage['login'])->toBe($neverSaved['login'])
        ->and($frontPage['login']['enabled'])->toBeFalse()
        ->and($frontPage['mode'])->toBe($neverSaved['mode'])
        ->and($frontPage['mode']['declared'])->toBeFalse();
});

it('reads each as an older release stored it until a cron request has run, and asks for none after', function () {
    OptionRow::storeUnloaded('corex_login_protection_settings', [
        'enabled' => true,
        'custom_slug' => 'team-door',
        'block_default_endpoints' => true,
    ]);
    OptionRow::storeUnloaded('corex_operations_mode', 'staging');
    OptionRow::storeUnloaded('corex_email_studio_defaults_version', $this->rows['corex_email_studio_defaults_version']['value'] ?? '3');

    // The files of this release are in place and no cron or admin request has run yet.
    $justDeployed = everyRequestOptionsReadBy('front page');
    everyRequestOptionsReadBy('cron');
    $frontPage = everyRequestOptionsReadBy('front page');

    expect($justDeployed['asked_one_at_a_time'])->toBe(COREX_OPTIONS_EVERY_REQUEST_READS)
        ->and($justDeployed['login'])->toBe(['enabled' => true, 'slug' => 'team-door'])
        ->and($justDeployed['mode'])->toBe(['current' => 'staging', 'declared' => true])
        ->and($frontPage['template_redirect'])->toBeTrue()
        ->and($frontPage['asked_one_at_a_time'])->toBe([])
        ->and($frontPage['login'])->toBe($justDeployed['login'])
        ->and($frontPage['mode'])->toBe($justDeployed['mode']);
});
