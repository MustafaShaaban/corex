<?php

/**
 * Which plugin or theme a fatal error came from (spec 118, OVR-D3). No WordPress.
 *
 * Contract: only the kind and the folder's name are kept, never the path.
 *
 * @package Corex\Tests\Unit\Health
 */

declare(strict_types=1);

use Corex\Health\FatalErrorSource;

const FATAL_ROOTS = [
    FatalErrorSource::MU_PLUGIN => 'C:\\site\\wp-content\\mu-plugins',
    FatalErrorSource::PLUGIN    => 'C:\\site\\wp-content\\plugins',
    FatalErrorSource::THEME     => 'C:\\site\\wp-content\\themes',
    FatalErrorSource::CORE      => 'C:\\site\\',
];

it('names the plugin, theme or must-use plugin a file belongs to', function (string $file, string $kind, string $slug) {
    expect(FatalErrorSource::of($file, FATAL_ROOTS))->toBe(['kind' => $kind, 'slug' => $slug]);
})->with([
    'a plugin folder' => ['C:\\site\\wp-content\\plugins\\akismet\\class.akismet.php', FatalErrorSource::PLUGIN, 'akismet'],
    'a single-file plugin' => ['C:/site/wp-content/plugins/hello.php', FatalErrorSource::PLUGIN, 'hello'],
    'a theme' => ['C:\\site\\wp-content\\themes\\corex\\functions.php', FatalErrorSource::THEME, 'corex'],
    'a must-use plugin' => ['C:\\site\\wp-content\\mu-plugins\\fixture.php', FatalErrorSource::MU_PLUGIN, 'fixture'],
    'WordPress itself' => ['C:\\site\\wp-includes\\load.php', FatalErrorSource::CORE, 'wordpress'],
    'somewhere else' => ['D:\\elsewhere\\x.php', FatalErrorSource::UNKNOWN, ''],
]);
