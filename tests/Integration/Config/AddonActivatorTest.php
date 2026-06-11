<?php

/**
 * Integration test: the add-on activator keeps the feature flag in sync with the toggle,
 * against the real ./wp install (spec 026 US1: FR-002). The flag arm is exercised here;
 * plugin (de)activation is covered by the live verification (quickstart §3/§4).
 *
 * @package Corex\Tests\Integration\Config
 */

declare(strict_types=1);

use Corex\Config\Addons\Addon;
use Corex\Config\Addons\AddonActivator;

afterEach(function () {
    delete_option('corex_features_addon_test_flag');
});

it('turns a flag on when enabling and off when disabling a flagged add-on', function () {
    // A non-existent plugin file is fine — activate_plugins/deactivate_plugins no-op on it;
    // we are asserting the flag arm stays in sync.
    $addon = new Addon('corex-test-addon', 'corex-test-addon/corex-test-addon.php', 'Test', flag: 'addon_test_flag');
    $activator = new AddonActivator();

    $activator->enable($addon);
    expect(get_option('corex_features_addon_test_flag'))->toBe('1');

    $activator->disable($addon);
    expect(get_option('corex_features_addon_test_flag'))->toBeFalse();
});
