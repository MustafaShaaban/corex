<?php

/**
 * An avatar on a CoreX theme page loads lazily (spec 121, T031; spec 116 FR-020).
 *
 * WordPress leaves `loading` off the first images inside the main loop, and on a single post the
 * first one can be a comment's avatar. With the heading scale of spec 121 that avatar sits below a
 * phone's first screen (895px against 812px on /hello-world/) and loaded at once. An avatar is
 * never a page's main image, so it is lazy unless its caller asked otherwise.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Theme\AvatarLazyLoading;
use Corex\Theme\ThemeDefaultPresets;

/**
 * The filter's answer for WordPress's attributes, the image's own and its context.
 *
 * @param array<string,string> $loading
 * @param array<string,mixed>  $image
 * @return array<string,string>
 */
function avatar_lazy_loading_for(
    array $loading,
    array $image = ['width' => 40, 'height' => 40],
    string $context = 'get_avatar',
    string $template = ThemeDefaultPresets::THEME,
    bool $lazyEnabled = true,
): array {
    Functions\when('get_template')->justReturn($template);
    Functions\when('wp_lazy_loading_enabled')->justReturn($lazyEnabled);

    return (new AvatarLazyLoading())->filter($loading, 'img', $image, $context);
}

it('makes an avatar lazy and drops a high fetch priority on the CoreX theme (spec 121 T031, spec 116 FR-020)', function () {
    expect(avatar_lazy_loading_for(['decoding' => 'async', 'fetchpriority' => 'high']))
        ->toBe(['decoding' => 'async', 'loading' => 'lazy']);
});

it('keeps the loading value an avatar\'s caller asked for', function (string $asked) {
    $loading = ['decoding' => 'async', 'fetchpriority' => 'high'];

    expect(avatar_lazy_loading_for($loading, ['width' => 40, 'height' => 40, 'loading' => $asked]))->toBe($loading);
})->with(['eager', 'lazy']);

it('leaves every image that is not an avatar as WordPress answered', function (string $context) {
    $loading = ['decoding' => 'async', 'fetchpriority' => 'high'];

    expect(avatar_lazy_loading_for($loading, context: $context))->toBe($loading);
})->with(['the_content', 'the_post_thumbnail', 'template_part_header']);

it('leaves a client theme\'s avatars as WordPress answered', function () {
    $loading = ['decoding' => 'async'];

    expect(avatar_lazy_loading_for($loading, template: 'client-theme'))->toBe($loading);
});

it('adds no lazy loading where the site turned it off', function () {
    $loading = ['decoding' => 'async'];

    expect(avatar_lazy_loading_for($loading, lazyEnabled: false))->toBe($loading);
});
