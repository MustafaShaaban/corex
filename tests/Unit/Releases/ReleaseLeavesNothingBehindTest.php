<?php

/**
 * Unit test: a file the new release no longer ships does not survive it (spec 107, FR-027;
 * plan D18).
 *
 * On 2026-10-11 a client site was updated by extracting a zip over the release before it.
 * Extracting adds and overwrites and removes nothing: four pattern files of the earlier theme
 * stayed beside the new theme's, WordPress read them when the Site Editor opened, and the
 * editor answered with a critical error until they were deleted by hand.
 *
 * An installation replaces each folder a release owns whole. This is that site: no record of
 * what the running release owns, and files in its folders that no release holds any more.
 *
 * @package Corex\Tests\Unit\Releases
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Support\ReleaseSite;

/** A site that has been extracted over, twice: the theme still holds patterns of the release before. */
const EXTRACTED_OVER = [
    'wp-content/themes/corex/style.css'                 => '/* 0.43 */',
    'wp-content/themes/corex/patterns/hero.php'         => '<?php // still in 0.44',
    'wp-content/themes/corex/patterns/old-pricing.php'  => '<?php // left by 0.42, registers a category that is gone',
    'wp-content/themes/corex/patterns/old-team.php'     => '<?php // left by 0.42',
    'wp-content/plugins/corex-core/corex-core.php'      => '<?php // core 0.43',
    'wp-content/plugins/corex-core/src/Removed.php'     => '<?php // left by 0.42',
    'wp-content/plugins/fluent-smtp/fluent-smtp.php'    => '<?php // no release named it',
    'wp-content/themes/twentytwentyfive/style.css'      => '/* not CoreX */',
    'wp-content/languages/corex-ar.mo'                  => 'a translation',
    'wp-content/uploads/2026/10/logo.png'               => 'a logo',
    'corex-release.json'                                => '{"corex_version":"0.43.5"}',
    'wp-config.php'                                     => '<?php // the site\'s own',
];

const THE_RELEASE_AFTER_IT = [
    'wp-content/themes/corex/style.css'            => '/* 0.44 */',
    'wp-content/themes/corex/patterns/hero.php'    => '<?php // hero 0.44',
    'wp-content/plugins/corex-core/corex-core.php' => '<?php // core 0.44',
];

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('wp_mkdir_p')->alias(static fn (string $path): bool => is_dir($path) || mkdir($path, 0777, true));
    Functions\when('wp_json_encode')->alias('json_encode');

    $this->site = new ReleaseSite(EXTRACTED_OVER, THE_RELEASE_AFTER_IT);
    // The site recorded nothing of what it owns: it was only ever extracted.
    $this->site->swap()->swap($this->site->manifest, []);
});

afterEach(function () {
    $this->site->remove();
});

it('leaves in a folder of the release only what the package holds for it', function () {
    $inTheReleasesFolders = array_filter(
        $this->site->files(),
        static fn (string $path): bool => str_starts_with($path, 'wp-content/themes/corex/') || str_starts_with($path, 'wp-content/plugins/corex-core/'),
        ARRAY_FILTER_USE_KEY,
    );

    expect(array_keys($inTheReleasesFolders))->toBe([
        'wp-content/plugins/corex-core/corex-core.php',
        'wp-content/themes/corex/patterns/hero.php',
        'wp-content/themes/corex/style.css',
    ]);
});

it('keeps what was left behind with the previous release, not on the site', function () {
    $previous = $this->site->root . '/wp-content/corex-releases/previous/wp-content';

    expect(is_file($previous . '/themes/corex/patterns/old-pricing.php'))->toBeTrue()
        ->and(is_file($previous . '/themes/corex/patterns/old-team.php'))->toBeTrue()
        ->and(is_file($previous . '/plugins/corex-core/src/Removed.php'))->toBeTrue();
});

it('removes nothing outside the folders the release owns', function () {
    $files = $this->site->files();

    foreach ([
        'wp-content/plugins/fluent-smtp/fluent-smtp.php',
        'wp-content/themes/twentytwentyfive/style.css',
        'wp-content/languages/corex-ar.mo',
        'wp-content/uploads/2026/10/logo.png',
        'corex-release.json',
        'wp-config.php',
    ] as $path) {
        expect($files[$path] ?? null)->toBe(EXTRACTED_OVER[$path]);
    }
});
