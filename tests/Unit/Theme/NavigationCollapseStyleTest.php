<?php

/**
 * Spec 137, slice 1 (US-NAV-11; FR-002 to FR-006; DECISIONS #692): the breakpoint writer.
 *
 * The collapse stylesheet is written with the CoreX theme's width. With the token at that width
 * the file is served; with any other accepted width the same rules are printed inline with the
 * token's width in their conditions. Where neither the active theme nor its parent ships the
 * file, nothing happens. The rules load where a Navigation block renders, and wherever WordPress
 * loads every block's style, which is the editor's canvas.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Tests\Support\ThemeContract;
use Corex\Theme\NavigationBreakpoint;
use Corex\Theme\NavigationCollapseStyle;

/**
 * Stub the WordPress calls the writer makes and record what it registers and enqueues.
 *
 * @param mixed       $token  What `wp_get_global_settings()` answers for the token.
 * @param string|null $themes The folder that holds `assets/css/`, or null for a theme without the file.
 * @return ArrayObject<string, mixed> What was registered, added inline and enqueued.
 */
function corexCollapseStubs(mixed $token, ?string $themes = null, bool $onDemand = true): ArrayObject
{
    if (! defined('COREX_CORE_VERSION')) {
        define('COREX_CORE_VERSION', 'test');
    }

    $themes ??= ThemeContract::root() . '/theme';
    $seen = new ArrayObject(['settings' => [], 'registered' => [], 'data' => [], 'inline' => [], 'enqueued' => []]);

    Functions\when('get_theme_file_path')->alias(
        static fn (string $relative): string => $themes . '/' . $relative,
    );
    Functions\when('get_theme_file_uri')->alias(
        static fn (string $relative): string => 'https://example.test/theme/' . $relative,
    );
    Functions\when('wp_get_global_settings')->alias(
        static function (array $path) use ($seen, $token): mixed {
            $seen['settings'] = [...$seen['settings'], $path];

            return $token;
        },
    );
    Functions\when('wp_should_load_block_assets_on_demand')->justReturn($onDemand);
    Functions\when('wp_style_is')->alias(
        static fn (string $handle, string $status): bool => $status === 'registered'
            && array_key_exists($handle, $seen['registered']),
    );
    Functions\when('wp_register_style')->alias(
        static function (string $handle, string|false $src, array $deps = [], string|bool|null $ver = false) use ($seen): bool {
            $seen['registered'] = [...$seen['registered'], $handle => ['src' => $src, 'deps' => $deps, 'ver' => $ver]];

            return true;
        },
    );
    Functions\when('wp_style_add_data')->alias(
        static function (string $handle, string $key, mixed $value) use ($seen): bool {
            $seen['data'] = [...$seen['data'], $handle . ':' . $key => $value];

            return true;
        },
    );
    Functions\when('wp_add_inline_style')->alias(
        static function (string $handle, string $css) use ($seen): bool {
            $seen['inline'] = [...$seen['inline'], $handle => $css];

            return true;
        },
    );
    Functions\when('wp_enqueue_style')->alias(
        static function (string $handle) use ($seen): void {
            $seen['enqueued'] = [...$seen['enqueued'], $handle];
        },
    );

    return $seen;
}

function corexCollapse(): NavigationCollapseStyle
{
    return new NavigationCollapseStyle(new NavigationBreakpoint());
}

function corexCollapseFile(): string
{
    return (string) file_get_contents(ThemeContract::root() . '/theme/' . NavigationCollapseStyle::RELATIVE_PATH);
}

it('serves the collapse stylesheet as a file when the token is the default (FR-004)', function () {
    $seen = corexCollapseStubs('782px');

    corexCollapse()->enqueue();

    expect($seen['settings'])->toBe([['custom', 'nav', 'breakpoint']])
        ->and($seen['registered'])->toBe([
            'corex-navigation-collapse' => [
                'src' => 'https://example.test/theme/assets/css/corex-navigation-collapse.css',
                'deps' => [],
                'ver' => 'test',
            ],
        ])
        ->and($seen['data'])->toBe([
            'corex-navigation-collapse:path' => ThemeContract::root() . '/theme/assets/css/corex-navigation-collapse.css',
        ])
        ->and($seen['inline'])->toBe([])
        ->and($seen['enqueued'])->toBe(['corex-navigation-collapse']);
});

it('prints the rules inline with a child\'s width in place of the default (FR-004, FR-005)', function () {
    $seen = corexCollapseStubs('1024px');

    corexCollapse()->enqueue();

    $css = $seen['inline']['corex-navigation-collapse'] ?? '';

    expect($seen['registered']['corex-navigation-collapse']['src'] ?? null)->toBeFalse()
        ->and($seen['data'])->toBe([])
        ->and($css)->toBe(str_replace('782px', '1024px', corexCollapseFile()))
        ->and($css)->toContain('(width >= 1024px)')
        ->and($css)->toContain('(600px <= width < 1024px)')
        ->and($css)->not->toContain('782px')
        ->and($seen['enqueued'])->toBe(['corex-navigation-collapse']);
});

it('serves the file, with nothing of the token on the page, for a value that is not a number of px (FR-004)', function (mixed $token) {
    $seen = corexCollapseStubs($token);

    corexCollapse()->enqueue();

    expect($seen['registered']['corex-navigation-collapse']['src'] ?? null)
        ->toBe('https://example.test/theme/assets/css/corex-navigation-collapse.css')
        ->and($seen['inline'])->toBe([])
        ->and($seen['enqueued'])->toBe(['corex-navigation-collapse']);
})->with([
    'another unit' => ['64em'],
    'no unit' => ['1024'],
    'negative' => ['-800px'],
    'garbage' => ['1024px) { body { display: none } } @media (width >= 1px'],
    'not set' => [['custom' => []]],
]);

it('prints the rules with 600px for a token below it (FR-004)', function () {
    $seen = corexCollapseStubs('480px');

    corexCollapse()->enqueue();

    $css = $seen['inline']['corex-navigation-collapse'] ?? '';

    expect($css)->toContain('(width >= 600px)')
        ->and($css)->toContain('(600px <= width < 600px)')
        ->and($css)->not->toContain('480px')
        ->and($css)->not->toContain('782px');
});

it('does nothing when neither the theme nor its parent ships the collapse stylesheet (FR-006)', function () {
    $seen = corexCollapseStubs('1024px', sys_get_temp_dir() . '/corex-no-such-theme-' . uniqid());
    $collapse = corexCollapse();

    $collapse->enqueue();
    $collapse->enqueueOnRender('<nav></nav>', ['blockName' => 'core/navigation']);

    expect($seen['settings'])->toBe([])
        ->and($seen['registered'])->toBe([])
        ->and($seen['inline'])->toBe([])
        ->and($seen['enqueued'])->toBe([]);
});

it('does not register the rules twice in one style registry, and enqueues them each time asked', function () {
    $seen = corexCollapseStubs('1024px');
    $collapse = corexCollapse();

    $collapse->enqueue();
    $collapse->enqueue();

    expect(array_keys($seen['registered']))->toBe(['corex-navigation-collapse'])
        ->and($seen['enqueued'])->toBe(['corex-navigation-collapse', 'corex-navigation-collapse']);
});

it('loads the rules on the page only where a Navigation block renders (constitution VI, FR-044)', function () {
    $seen = corexCollapseStubs('782px');
    $collapse = corexCollapse();

    expect($collapse->enqueueOnRender('<p>x</p>', ['blockName' => 'core/paragraph']))->toBe('<p>x</p>')
        ->and($seen['enqueued'])->toBe([]);

    $collapse->enqueueWhereEveryBlockLoads();
    expect($seen['enqueued'])->toBe([]);

    expect($collapse->enqueueOnRender('<nav></nav>', ['blockName' => 'core/navigation']))->toBe('<nav></nav>')
        ->and($seen['enqueued'])->toBe(['corex-navigation-collapse']);
});

it('loads the rules wherever WordPress loads every block\'s style, the editor\'s canvas among them (FR-004)', function () {
    $seen = corexCollapseStubs('1024px', onDemand: false);
    $collapse = corexCollapse();

    // Not on demand: a block rendered here (a REST preview, a feed) does not add the rules itself.
    $collapse->enqueueOnRender('<nav></nav>', ['blockName' => 'core/navigation']);
    expect($seen['enqueued'])->toBe([]);

    $collapse->enqueueWhereEveryBlockLoads();
    expect($seen['enqueued'])->toBe(['corex-navigation-collapse'])
        ->and($seen['inline']['corex-navigation-collapse'] ?? '')->toContain('(width >= 1024px)');
});
