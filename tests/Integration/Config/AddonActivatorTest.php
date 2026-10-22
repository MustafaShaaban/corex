<?php

/**
 * Turning an add-on on or off says what WordPress did (spec 114, US-SAFE-08), against the real
 * ./wp install.
 *
 * The activator ignored WordPress's answer: an add-on whose file was missing was reported
 * "Enabled", its feature flag was written, and a kit prompt queued. A must-use add-on could not
 * be switched off, yet its flag was deleted. Two throwaway plugins stand in for add-ons, written
 * into the plugins folder for the test and removed after it.
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Activity\ActivityService;
use Corex\Boot;
use Corex\Config\Addons\Addon;
use Corex\Config\Addons\AddonToggleService;
use Corex\Multisite\ActivationScope;

const COREX_TOGGLE_PLUGIN = 'corex-test-toggle/corex-test-toggle.php';
const COREX_NOISY_PLUGIN  = 'corex-test-noisy/corex-test-noisy.php';

beforeEach(function () {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
    $this->signedInAs = get_current_user_id();
    $administrators   = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    $this->admin      = (int) ($administrators[0] ?? 0);
    wp_set_current_user($this->admin);

    $write = static function (string $file, string $body): void {
        wp_mkdir_p(dirname(WP_PLUGIN_DIR . '/' . $file));
        file_put_contents(WP_PLUGIN_DIR . '/' . $file, "<?php\n/**\n * Plugin Name: CoreX toggle test\n */\n" . $body);
    };
    $write(COREX_TOGGLE_PLUGIN, '');
    // A plugin that prints while it is activated: WordPress refuses it as unexpected output.
    $write(COREX_NOISY_PLUGIN, "echo 'hello';\n");
    wp_clean_plugins_cache();

    $this->toggles = Boot::app()->container()->make(AddonToggleService::class);
    $this->addon   = static fn (string $file): Addon => new Addon('corex-test-toggle', $file, 'Toggle test', flag: 'addon_test_flag');
});

afterEach(function () {
    deactivate_plugins([COREX_TOGGLE_PLUGIN, COREX_NOISY_PLUGIN], true, false);
    foreach ([COREX_TOGGLE_PLUGIN, COREX_NOISY_PLUGIN] as $file) {
        @unlink(WP_PLUGIN_DIR . '/' . $file);
        @rmdir(dirname(WP_PLUGIN_DIR . '/' . $file));
    }
    wp_clean_plugins_cache();
    delete_option('corex_features_addon_test_flag');
    wp_set_current_user($this->signedInAs);
});

/** The newest activity event about the test add-on. */
function lastToggleEvent(): ?\Corex\Activity\ActivityEvent
{
    $events = Boot::app()->container()->make(ActivityService::class)->query(['area' => 'addons'], 1, 1);

    return $events[0] ?? null;
}

it('turns a working add-on on and off, with its flag, and logs both', function () {
    $addon = ($this->addon)(COREX_TOGGLE_PLUGIN);

    $on = $this->toggles->enable($addon, $this->admin);
    expect($on->succeeded)->toBeTrue()
        ->and(is_plugin_active(COREX_TOGGLE_PLUGIN))->toBeTrue()
        ->and(get_option('corex_features_addon_test_flag'))->toBe('1')
        ->and(lastToggleEvent()?->kind)->toBe('addon.enabled')
        ->and(lastToggleEvent()?->outcome)->toBe('success')
        ->and(lastToggleEvent()?->actorId)->toBe($this->admin);

    $off = $this->toggles->disable($addon, ActivationScope::Site, $this->admin);
    expect($off->succeeded)->toBeTrue()
        ->and(is_plugin_active(COREX_TOGGLE_PLUGIN))->toBeFalse()
        ->and(get_option('corex_features_addon_test_flag'))->toBeFalse()
        ->and(lastToggleEvent()?->kind)->toBe('addon.disabled');
});

it('says WordPress refused an add-on whose file is missing, and writes no flag', function () {
    $result = $this->toggles->enable(($this->addon)('corex-test-missing/corex-test-missing.php'), $this->admin);

    expect($result->succeeded)->toBeFalse()
        ->and($result->reason)->toBe('Plugin file does not exist.')
        ->and(get_option('corex_features_addon_test_flag'))->toBeFalse()
        ->and(lastToggleEvent()?->outcome)->toBe('failure')
        ->and(lastToggleEvent()?->context['reason'] ?? '')->toBe('Plugin file does not exist.');
});

it('says WordPress refused an add-on that printed while it was activated', function () {
    $result = $this->toggles->enable(($this->addon)(COREX_NOISY_PLUGIN), $this->admin);

    expect($result->succeeded)->toBeFalse()
        ->and($result->reason)->toBe('The plugin generated unexpected output.')
        ->and(is_plugin_active(COREX_NOISY_PLUGIN))->toBeFalse()
        ->and(get_option('corex_features_addon_test_flag'))->toBeFalse();
});

it('refuses to switch off an add-on this site does not control, and keeps its flag', function (ActivationScope $scope, string $reason) {
    $addon = ($this->addon)(COREX_TOGGLE_PLUGIN);
    activate_plugin(COREX_TOGGLE_PLUGIN);
    update_option('corex_features_addon_test_flag', '1');

    $result = $this->toggles->disable($addon, $scope, $this->admin);

    expect($result->succeeded)->toBeFalse()
        ->and($result->reason)->toBe($reason)
        ->and(is_plugin_active(COREX_TOGGLE_PLUGIN))->toBeTrue()
        ->and(get_option('corex_features_addon_test_flag'))->toBe('1')
        ->and(lastToggleEvent()?->outcome)->toBe('failure');
})->with([
    'active for the network' => [ActivationScope::Network, 'Managed by the network administrator'],
    'must-use' => [ActivationScope::MustUse, 'Must-use: always on'],
]);
