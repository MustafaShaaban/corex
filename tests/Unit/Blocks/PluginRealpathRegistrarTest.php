<?php

/**
 * Unit tests for the pure part of the plugin-realpath registrar (spec 040). No WordPress —
 * synthetic mount maps only. The registrar replays a junctioned add-on's real on-disk location
 * to WordPress so `plugin_basename()` can map a realpath()-resolved asset path back under
 * WP_PLUGIN_DIR (otherwise add-ons Corex loads itself — not via WP's active_plugins — emit a
 * broken `…/plugins/C:/…` asset URL).
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\PluginRealpathRegistrar;

const RR_PLUGINS_DIR = 'C:/wamp64/www/corex/wp/wp-content/plugins';

it('returns the main-file path for each junctioned/symlinked mount', function () {
    $mounts = [
        // Junctions: the real target is outside the plugins dir.
        'C:/wamp64/www/corex/addons/corex-careers'       => 'corex-careers',
        'C:/wamp64/www/corex/addons/corex-kit-portfolio' => 'corex-kit-portfolio',
        'C:/wamp64/www/corex/plugins/corex-blocks'       => 'corex-blocks',
    ];

    $files = PluginRealpathRegistrar::pluginFiles($mounts, RR_PLUGINS_DIR);

    expect($files)->toBe([
        RR_PLUGINS_DIR . '/corex-careers/corex-careers.php',
        RR_PLUGINS_DIR . '/corex-kit-portfolio/corex-kit-portfolio.php',
        RR_PLUGINS_DIR . '/corex-blocks/corex-blocks.php',
    ]);
});

it('skips a real directory that already lives under the plugins dir (WP already knows it)', function () {
    $mounts = [
        RR_PLUGINS_DIR . '/woocommerce'              => 'woocommerce',
        'C:/wamp64/www/corex/addons/corex-careers'   => 'corex-careers',
    ];

    $files = PluginRealpathRegistrar::pluginFiles($mounts, RR_PLUGINS_DIR);

    expect($files)->toBe([RR_PLUGINS_DIR . '/corex-careers/corex-careers.php']);
});

it('tolerates Windows backslashes and drive-letter casing when comparing', function () {
    $mounts = [
        'c:\\wamp64\\www\\corex\\wp\\wp-content\\plugins\\acf' => 'acf', // same place, different form → skip
    ];

    expect(PluginRealpathRegistrar::pluginFiles($mounts, RR_PLUGINS_DIR))->toBe([]);
});
