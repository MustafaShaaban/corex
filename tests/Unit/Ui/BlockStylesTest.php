<?php

/**
 * Unit tests for the DLS block-style registrar (spec 054, US3). The styles are appearance-only
 * variants on core blocks (research D4) — delivered via register_block_style(), not new blocks.
 * Spec 134 (FR-013, DECISIONS #640, #642) adds the three section styles of the Group block: the quiet
 * band, the dark band and the brass band, with their own sheet, only under a theme that defines the
 * `band` custom colour or whose palette has every slug their rules fall back to. The question is asked
 * only when an answer is needed: by the editor, or when a band is drawn (never on `init`).
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\BlockStyles;

/** @return list<array{slug:string,color:string,name:string}> a palette with the given slugs */
function blockStylesPalette(string ...$slugs): array
{
    return array_map(static fn (string $slug): array => ['slug' => $slug, 'color' => '#000000', 'name' => $slug], $slugs);
}

/**
 * Answers `wp_get_global_settings()` for a theme with these palette slugs and custom colours.
 *
 * @param list<string> $slugs
 * @param array<string,string> $custom
 */
function blockStylesTheme(array $slugs, array $custom = []): void
{
    Functions\when('wp_get_global_settings')->alias(static fn (array $path): array => match ($path) {
        ['color', 'palette', 'theme'] => blockStylesPalette(...$slugs),
        ['custom', 'color'] => $custom,
        default => [],
    });
}

/** The CoreX theme's roles, as a theme built on them names them. */
const BLOCK_STYLES_COREX_SLUGS = ['primary', 'primary-dark', 'accent', 'accent-dark', 'surface', 'surface-alt', 'ink', 'ink-soft', 'border', 'inverse'];

/** The thirteen roles `BrandOverrideValidator` asks of a brand palette, which replaces the theme's whole. */
const BLOCK_STYLES_BRAND_SLUGS = ['primary', 'primary-dark', 'accent', 'accent-dark', 'surface', 'surface-alt', 'border', 'ink', 'ink-soft', 'success', 'warning', 'error', 'info'];

/** Perego's and Muva's own palettes (read from their theme.json): none has the CoreX roles. */
const BLOCK_STYLES_CLIENT_SLUGS = [
    'perego-like' => [['bg-deep', 'violet', 'accent', 'accent-soft', 'panel', 'text']],
    'muva-like'   => [['night', 'black', 'white', 'green', 'error', 'panel', 'text', 'stat', 'card', 'ink']],
];

/** @return array<string,string> the CoreX theme's custom colours, as `settings.custom.color` holds them */
function blockStylesCorexCustom(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/theme.json'), true)['settings']['custom']['color'];
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->styles = new BlockStyles(
        'https://example.test/wp-content/plugins/corex-ui/assets/block-styles.css',
        'https://example.test/wp-content/plugins/corex-ui/assets/section-bands.css',
    );
    if (! defined('COREX_UI_VERSION')) {
        define('COREX_UI_VERSION', 'test');
    }
});

it('declares the DLS block styles on their core blocks', function () {
    $names = array_column($this->styles->styles(), 'name');

    expect($names)->toEqual(['corex-card', 'corex-section', 'corex-empty', 'corex-striped', 'corex-secondary', 'corex-ghost']);

    foreach ([...$this->styles->styles(), ...$this->styles->bands()] as $style) {
        expect($style['block'])->toStartWith('core/')
            ->and($style['label'])->not->toBe('');
    }
});

// Spec 134, FR-013: the three section styles are Group styles, named for the editor in words.
it('declares the quiet, dark and brass bands as section styles of the Group block (FR-013)', function () {
    expect($this->styles->bands())->toBe([
        ['block' => 'core/group', 'name' => 'corex-band-quiet', 'label' => 'Quiet band'],
        ['block' => 'core/group', 'name' => 'corex-band-dark',  'label' => 'Dark band'],
        ['block' => 'core/group', 'name' => 'corex-band-brass', 'label' => 'Brass band'],
    ]);
});

it('translates each section style\'s label in the corex domain (FR-013)', function () {
    Functions\when('__')->alias(
        static fn (string $text, string $domain): string => $domain === 'corex' ? "[{$text}]" : $text,
    );

    expect(array_column($this->styles->bands(), 'label'))->toBe(['[Quiet band]', '[Dark band]', '[Brass band]']);
});

// DECISIONS #642: the bands exist where the theme defines the band colour, or can draw every fallback.
it('offers the bands under a theme that defines the band colour, whatever palette a brand gives it', function () {
    expect($this->styles->bandsAvailable(BLOCK_STYLES_BRAND_SLUGS, blockStylesCorexCustom()))->toBeTrue()
        ->and($this->styles->bandsAvailable([], ['band' => '#14151a']))->toBeTrue();
});

it('offers the bands under a theme whose palette has every slug their rules fall back to', function () {
    expect($this->styles->bandsAvailable(BLOCK_STYLES_COREX_SLUGS, []))->toBeTrue()
        ->and($this->styles->bandsAvailable(BlockStyles::BAND_SLUGS, []))->toBeTrue();

    foreach (BlockStyles::BAND_SLUGS as $missing) {
        expect($this->styles->bandsAvailable(array_values(array_diff(BLOCK_STYLES_COREX_SLUGS, [$missing])), []))
            ->toBeFalse("a theme without {$missing}");
    }
});

it('offers no band under a client theme with slugs of its own', function (array $slugs) {
    expect($this->styles->bandsAvailable($slugs, []))->toBeFalse();
})->with(BLOCK_STYLES_CLIENT_SLUGS);

it('registers the six styles on init, and asks nothing of the theme there', function () {
    Functions\expect('wp_get_global_settings')->never();
    Functions\expect('wp_register_style')->once()->with(BlockStyles::HANDLE, \Mockery::pattern('/block-styles\.css$/'), [], \Mockery::any());
    $registered = [];
    Functions\when('register_block_style')->alias(static function (string $block, array $args) use (&$registered): void {
        $registered[$args['name']] = $args['style_handle'];
    });

    $this->styles->register();

    expect($registered)->toBe(array_fill_keys(
        ['corex-card', 'corex-section', 'corex-empty', 'corex-striped', 'corex-secondary', 'corex-ghost'],
        BlockStyles::HANDLE,
    ))
        ->and(has_filter('render_block', [$this->styles, 'enqueueWhenStyled']))->toBe(10)
        // The editor's style picker is written at priority 10, its canvas sheets at 30.
        ->and(has_action('enqueue_block_editor_assets', [$this->styles, 'registerBands']))->toBe(5)
        ->and(has_action('enqueue_block_assets', [$this->styles, 'registerBandsForEditor']))->toBe(5);
});

it('registers the bands on their own sheet, once a request, under a theme that can draw them', function (array $slugs, array $custom) {
    blockStylesTheme($slugs, $custom);
    Functions\expect('wp_register_style')->once()->with(BlockStyles::BANDS_HANDLE, \Mockery::pattern('/section-bands\.css$/'), [], \Mockery::any());
    $registered = [];
    Functions\when('register_block_style')->alias(static function (string $block, array $args) use (&$registered): void {
        $registered[$args['name']] = $args['style_handle'];
    });

    $this->styles->registerBands();
    $this->styles->registerBands();

    expect($registered)->toBe(array_fill_keys(['corex-band-quiet', 'corex-band-dark', 'corex-band-brass'], BlockStyles::BANDS_HANDLE));
})->with([
    'the CoreX roles' => [BLOCK_STYLES_COREX_SLUGS, []],
    'a brand palette of the thirteen roles, on the CoreX theme' => [BLOCK_STYLES_BRAND_SLUGS, ['band' => '#14151a']],
]);

it('registers no band and no band sheet under a client theme with slugs of its own', function (array $slugs) {
    blockStylesTheme($slugs);
    Functions\expect('wp_register_style')->never();
    Functions\expect('register_block_style')->never();

    $this->styles->registerBands();
})->with(BLOCK_STYLES_CLIENT_SLUGS);

it('registers the bands for the editor canvas in the admin only', function () {
    Functions\when('is_admin')->justReturn(false);
    Functions\expect('wp_get_global_settings')->never();

    $this->styles->registerBandsForEditor();
});

/*
 * Spec 134, DECISIONS #643: WordPress enqueues a block style's sheet from `enqueue_block_assets`, which
 * a block theme fires in `wp_head`, after it has drawn the template; so the sheet never reached the
 * page. It is enqueued as a block that uses one of these styles is drawn.
 */
it('enqueues a style\'s sheet when a block that uses it is drawn', function (array $block, string $handle) {
    Functions\when('wp_style_is')->justReturn(true);
    Functions\expect('wp_enqueue_style')->once()->with($handle);

    expect($this->styles->enqueueWhenStyled('<section></section>', $block))->toBe('<section></section>');
})->with([
    'a card among other classes' => [['blockName' => 'core/group', 'attrs' => ['className' => 'alignwide is-style-corex-card my-card']], BlockStyles::HANDLE],
    'a ghost button' => [['blockName' => 'core/button', 'attrs' => ['className' => 'is-style-corex-ghost']], BlockStyles::HANDLE],
]);

it('asks the theme the first time a band is drawn, and enqueues the band sheet where it can draw it', function () {
    $asked = 0;
    Functions\when('wp_get_global_settings')->alias(static function (array $path) use (&$asked): array {
        $asked++;

        return $path === ['custom', 'color'] ? [] : blockStylesPalette(...BLOCK_STYLES_COREX_SLUGS);
    });
    Functions\when('register_block_style')->justReturn(true);
    $registered = false;
    Functions\when('wp_register_style')->alias(static function () use (&$registered): bool {
        return $registered = true;
    });
    Functions\when('wp_style_is')->alias(static function () use (&$registered): bool {
        return $registered;
    });
    Functions\expect('wp_enqueue_style')->twice()->with(BlockStyles::BANDS_HANDLE);

    foreach (['is-style-corex-band-dark', 'is-style-corex-band-brass'] as $class) {
        $this->styles->enqueueWhenStyled('<section></section>', ['blockName' => 'core/group', 'attrs' => ['className' => $class]]);
    }

    expect($asked)->toBe(2); // the palette and the custom colours, once
});

it('leaves the band sheet out where the theme cannot draw the bands', function () {
    blockStylesTheme(BLOCK_STYLES_CLIENT_SLUGS['perego-like'][0]);
    Functions\when('wp_style_is')->justReturn(false);
    Functions\expect('wp_enqueue_style')->never();

    $this->styles->enqueueWhenStyled('<section></section>', ['blockName' => 'core/group', 'attrs' => ['className' => 'is-style-corex-band-brass']]);
});

it('leaves the sheets out for a block that uses none of the styles, without asking for a label or the theme', function (array $block) {
    Functions\when('__')->alias(static fn (): never => throw new \LogicException('a label was built for a drawn block'));
    Functions\expect('wp_get_global_settings')->never();
    Functions\expect('wp_style_is')->never();
    Functions\expect('wp_enqueue_style')->never();

    expect($this->styles->enqueueWhenStyled('<p></p>', $block))->toBe('<p></p>');
})->with([
    'a plain group' => [['blockName' => 'core/group', 'attrs' => []]],
    'an empty class name' => [['blockName' => 'core/group', 'attrs' => ['className' => '']]],
    'another style' => [['blockName' => 'core/group', 'attrs' => ['className' => 'is-style-corex-band-darker']]],
    'a style of another block' => [['blockName' => 'core/paragraph', 'attrs' => ['className' => 'is-style-corex-card']]],
    'a freeform block' => [['blockName' => null, 'attrs' => []]],
]);
