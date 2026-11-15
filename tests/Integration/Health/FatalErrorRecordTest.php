<?php

/**
 * A request that ends in a PHP fatal error is recorded, and nothing else is (spec 118, US-OVR-04).
 *
 * A fatal error ends the process it happens in, so each request here is its own PHP process that
 * loads this WordPress: one calls a function that does not exist from a plugin of this test's own,
 * one does nothing. The record keeps the time, the error type and the plugin, nothing more; the
 * request without a fatal error writes nothing; and the Site health tile names the plugin to an
 * administrator and only the date to anyone else. The plugin and the record are removed after.
 *
 * @package Corex\Tests\Integration\Health
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Config\Overview\Cards\TilesCard;
use Corex\Health\FatalErrorLog;
use Corex\Tests\Support\AdminScreen;

beforeEach(function () {
    $this->saved  = get_option(FatalErrorLog::OPTION, null);
    delete_option(FatalErrorLog::OPTION);
    $this->plugin = WP_PLUGIN_DIR . '/corex-fatal-probe';
    wp_mkdir_p($this->plugin);
    file_put_contents($this->plugin . '/corex-fatal-probe.php', "<?php\n/**\n * Plugin Name: Corex Fatal Probe\n */\nfunction corex_fatal_probe() { corex_function_that_does_not_exist(); }\n");
    $this->runner = tempnam(sys_get_temp_dir(), 'corex-fatal') . '.php';
});

afterEach(function () {
    @unlink($this->plugin . '/corex-fatal-probe.php');
    @rmdir($this->plugin);
    @unlink($this->runner);
    $this->saved === null ? delete_option(FatalErrorLog::OPTION) : update_option(FatalErrorLog::OPTION, $this->saved, false);
    wp_clean_plugins_cache(false);
    wp_set_current_user(0);
    AdminScreen::set('front');
});

/** One request to this WordPress in a PHP process of its own; `$fatal` ends it in a fatal error. */
function fatalRequest(string $runner, bool $fatal): void
{
    file_put_contents($runner, "<?php\ndefine('WP_USE_THEMES', false);\ndefine('DISABLE_WP_CRON', true);\nrequire " . var_export(ABSPATH . 'wp-load.php', true) . ";\n"
        . ($fatal ? 'require ' . var_export(WP_PLUGIN_DIR . '/corex-fatal-probe/corex-fatal-probe.php', true) . ";\ncorex_fatal_probe();\n" : ''));
    shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($runner) . ' 2>&1');
}

/** The record as the database holds it now, not as this process cached it. */
function fatalRecord(): mixed
{
    wp_cache_delete(FatalErrorLog::OPTION, 'options');
    wp_cache_delete('notoptions', 'options');
    wp_cache_delete('alloptions', 'options');

    return get_option(FatalErrorLog::OPTION, null);
}

it('records the time, the type and the plugin of a fatal error, and nothing else', function () {
    fatalRequest($this->runner, true);

    $record = fatalRecord();

    expect($record)->toBeArray()->toHaveCount(1)
        ->and(array_keys($record[0]))->toBe(['time', 'type', 'kind', 'slug'])
        ->and($record[0]['type'])->toBe(E_ERROR)
        ->and($record[0]['kind'])->toBe('plugin')
        ->and($record[0]['slug'])->toBe('corex-fatal-probe');
});

it('writes nothing on a request without a fatal error', function () {
    fatalRequest($this->runner, false);

    expect(fatalRecord())->toBeNull();
});

it('names the plugin to an administrator and only the date to anyone else', function () {
    fatalRequest($this->runner, true);
    fatalRecord();
    wp_clean_plugins_cache(false);

    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));
    $forAdministrator = Boot::app()->container()->make(TilesCard::class)->render([]);
    wp_set_current_user(0);
    $forOthers = Boot::app()->container()->make(TilesCard::class)->render([]);

    expect($forAdministrator)->toContain('Last PHP fatal error:')
        ->and($forAdministrator)->toContain(', in Corex Fatal Probe')
        ->and($forOthers)->toContain('Last PHP fatal error:')
        ->and($forOthers)->not->toContain('Corex Fatal Probe');
});
