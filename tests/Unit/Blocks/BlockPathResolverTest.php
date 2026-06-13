<?php

/**
 * Unit tests for the pure block-path resolver (spec 040). No WordPress — synthetic paths only.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\BlockPathResolver;

const PLUGINS_DIR = 'C:/wamp64/www/corex/wp/wp-content/plugins';

/** @return array<string,string> */
function mountMap(): array
{
    // The junction `…/plugins/corex-ui` resolves to the real monorepo path.
    return ['C:/wamp64/www/corex/addons/corex-ui' => 'corex-ui'];
}

it('maps a realpath-resolved dir outside the plugins dir back under wp-content/plugins (the regression)', function () {
    $resolved = (new BlockPathResolver())->resolve(
        'C:/wamp64/www/corex/addons/corex-ui/build/blocks/posts',
        PLUGINS_DIR,
        mountMap(),
    );

    expect($resolved)->toBe(PLUGINS_DIR . '/corex-ui/build/blocks/posts');
});

it('returns an already-under-plugins dir unchanged (no regression for the junction case)', function () {
    $dir = PLUGINS_DIR . '/corex-ui/build/blocks/posts';

    expect((new BlockPathResolver())->resolve($dir, PLUGINS_DIR, mountMap()))->toBe($dir);
});

it('returns the original dir when no mount matches (never fabricates)', function () {
    $dir = 'D:/somewhere/else/build/blocks/posts';

    expect((new BlockPathResolver())->resolve($dir, PLUGINS_DIR, mountMap()))->toBe($dir);
});

it('tolerates Windows backslashes and drive-letter casing', function () {
    $resolved = (new BlockPathResolver())->resolve(
        'c:\\wamp64\\www\\corex\\addons\\corex-ui\\build\\blocks\\stat',
        PLUGINS_DIR,
        mountMap(),
    );

    expect($resolved)->toBe(PLUGINS_DIR . '/corex-ui/build/blocks/stat');
});

it('is idempotent', function () {
    $resolver = new BlockPathResolver();
    $once     = $resolver->resolve('C:/wamp64/www/corex/addons/corex-ui/build/blocks/posts', PLUGINS_DIR, mountMap());
    $twice    = $resolver->resolve($once, PLUGINS_DIR, mountMap());

    expect($twice)->toBe($once);
});
