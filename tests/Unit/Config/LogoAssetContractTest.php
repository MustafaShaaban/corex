<?php

/**
 * The CoreX logo set and its manifest (spec 057; spec 134, US-BRD-05, FR-018 to FR-024).
 *
 * Every file of `plugins/corex-config/assets/brand/` is written by `scripts/generate-logo-assets.py`
 * and listed in `logo-manifest.json` with its use, its colours and its hash. These tests hold the
 * folder and the manifest to each other, the drawings to the approved logo set
 * (`design/handoffs/spec-134-logo-set.md`), and the theme's favicon copies to the logo folder's
 * (DECISIONS #637).
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

const LOGO_FOLDER = 'plugins/corex-config/assets/brand';
const LOGO_MANIFEST = LOGO_FOLDER . '/logo-manifest.json';

/** The approved colours of the logo set (the hand-over's "Approved scope"). */
const LOGO_INK = [
    'light' => ['neutral' => '#14151a', 'brass' => '#ad8643'],
    'dark'  => ['neutral' => '#f4f5f7', 'brass' => '#c9a25e'],
];

/** @return array<string, mixed> */
function logo_manifest(): array
{
    return ThemeContract::json(LOGO_MANIFEST);
}

/** @return array<string, array<string, mixed>> Every asset entry, keyed by its variant. */
function logo_assets(): array
{
    $assets = [];

    foreach (logo_manifest()['assets'] as $asset) {
        $assets[$asset['variant']] = $asset;
    }

    return $assets;
}

/**
 * The drawn shapes of an SVG in document order, each with the fill it is painted in: its own, or
 * the nearest group's (svgo moves a shared fill onto a group).
 *
 * @return list<array{tag: string, fill: string, x: string, y: string, width: string}>
 */
function logo_shapes(string $relativePath): array
{
    $document = new DOMDocument();
    $document->loadXML((string) file_get_contents(ThemeContract::root() . '/' . $relativePath));
    $shapes = [];

    foreach ($document->getElementsByTagName('*') as $element) {
        if (! in_array($element->localName, ['rect', 'path'], true)) {
            continue;
        }

        $fill = '';

        for ($node = $element; $node instanceof DOMElement && $fill === ''; $node = $node->parentNode) {
            $fill = $node->getAttribute('fill');
        }

        $shapes[] = [
            'tag'   => $element->localName,
            'fill'  => strtolower($fill),
            'x'     => $element->getAttribute('x'),
            'y'     => $element->getAttribute('y'),
            'width' => $element->getAttribute('width'),
        ];
    }

    return $shapes;
}

/**
 * What a lockup paints each part in: the four corner modules, the centre module, the divider and
 * the word's two paths ("Core", then the terminal "x").
 *
 * @return array{corners: list<string>, centre: string, divider: ?string, core: string, x: string}
 */
function logo_parts(string $relativePath): array
{
    $shapes = logo_shapes($relativePath);
    $modules = array_values(array_filter($shapes, static fn (array $s): bool => $s['tag'] === 'rect' && $s['width'] === '12'));
    $dividers = array_values(array_filter($shapes, static fn (array $s): bool => $s['tag'] === 'rect' && $s['width'] === '1.5'));
    $paths = array_values(array_filter($shapes, static fn (array $s): bool => $s['tag'] === 'path'));
    $centre = array_values(array_filter($modules, static fn (array $s): bool => $s['x'] === '18' && $s['y'] === '18'));
    $corners = array_values(array_filter($modules, static fn (array $s): bool => ! ($s['x'] === '18' && $s['y'] === '18')));

    expect($corners)->toHaveCount(4, "{$relativePath} draws four corner modules")
        ->and($centre)->toHaveCount(1, "{$relativePath} draws the centre module")
        ->and($paths)->toHaveCount(2, "{$relativePath} draws the word as \"Core\" and the \"x\"");

    return [
        'corners' => array_column($corners, 'fill'),
        'centre'  => $centre[0]['fill'],
        'divider' => $dividers[0]['fill'] ?? null,
        'core'    => $paths[0]['fill'],
        'x'       => $paths[1]['fill'],
    ];
}

/** The colour of one pixel of a PNG, as `#rrggbb`, and its opacity from 0 to 1. */
function logo_pixel(string $relativePath, int $x, int $y): array
{
    $image = imagecreatefrompng(ThemeContract::root() . '/' . $relativePath);
    $index = imagecolorat($image, $x, $y);
    $colour = imagecolorsforindex($image, $index);

    return [
        sprintf('#%02x%02x%02x', $colour['red'], $colour['green'], $colour['blue']),
        round(1 - $colour['alpha'] / 127, 3),
    ];
}

it('defines accessible logo usage and size fixtures independently of assets', function () {
    $fixtures = ThemeContract::json('tests/Fixtures/Branding/logo-usage.json')['scenarios'];

    expect(array_column($fixtures, 'usage'))->toEqualCanonicalizing([
        'decorative', 'named-image', 'linked-brand',
    ]);

    foreach ($fixtures as $fixture) {
        expect($fixture)->toHaveKeys([
            'id', 'usage', 'variant', 'minimum_inline_size_px', 'background', 'accessible_name',
        ])
            ->and($fixture['minimum_inline_size_px'])->toBeGreaterThanOrEqual(16)
            ->and($fixture['background'])->toBeIn(['light', 'dark']);

        if ($fixture['usage'] === 'decorative') {
            expect($fixture['accessible_name'])->toBe('');
        } else {
            expect($fixture['accessible_name'])->not->toBe('');
        }
    }
});

it('requires an owner approved provenance manifest before logo integration', function () {
    $manifest = logo_manifest();

    expect($manifest)->toHaveKeys(['source', 'author_or_owner', 'license_or_rights', 'approval_date', 'assets'])
        ->and($manifest['approval_date'])->toMatch('/^\d{4}-\d{2}-\d{2}$/');
});

it('holds the logo set the hand-over approved, each file in the manifest (FR-019, FR-021, TC-BRD-05-03)', function () {
    expect(array_keys(logo_assets()))->toEqualCanonicalizing([
        // Drawn inline, in the colour of the text around them (DECISIONS #637).
        'symbol', 'wordmark', 'lockup', 'monochrome', 'contrast',
        // Fixed colours, for a light and a dark background.
        'lockup-light', 'lockup-dark', 'stacked-light', 'stacked-dark', 'symbol-light', 'symbol-dark',
        // One ink, graphite, on a brass tile.
        'knockout',
        // The symbol on a graphite tile, as SVG and at the four sizes browsers and phones ask for.
        'favicon', 'favicon-16', 'favicon-32', 'favicon-180', 'favicon-512',
    ]);
});

it('lists every file of the logo folder once, with its use, its colours and its hash (FR-024)', function () {
    $manifest = logo_manifest();
    $listed = [...array_column($manifest['assets'], 'filename'), $manifest['print']['filename']];
    $onDisk = array_map('basename', glob(ThemeContract::root() . '/' . LOGO_FOLDER . '/*') ?: []);
    $onDisk = array_values(array_diff($onDisk, ['logo-manifest.json']));

    // A file with no entry, or an entry with no file, fails here, naming it.
    expect(array_values(array_diff($onDisk, $listed)))->toBe([], 'files the manifest does not list')
        ->and(array_values(array_diff($listed, $onDisk)))->toBe([], 'entries with no file')
        ->and(count($listed))->toBe(count(array_unique($listed)));

    foreach ($manifest['assets'] as $asset) {
        expect($asset)->toHaveKeys(['variant', 'path', 'filename', 'use', 'colors', 'sha256', 'accessible_usage'])
            ->and($asset['path'])->toBe(LOGO_FOLDER . '/' . $asset['filename'])
            ->and($asset['use'])->toBeString()->not->toBe('')
            ->and($asset['colors'])->toBeArray()->not->toBeEmpty()
            ->and(hash_file('sha256', ThemeContract::root() . '/' . $asset['path']))
            ->toBe($asset['sha256'], "{$asset['filename']} differs from the hash in the manifest");
    }
});

it('paints each SVG in exactly the colours its manifest entry names (FR-024)', function () {
    foreach (logo_assets() as $asset) {
        if (! str_ends_with($asset['filename'], '.svg')) {
            continue;
        }

        $fills = array_values(array_unique(array_column(logo_shapes($asset['path']), 'fill')));

        expect($fills)->toEqualCanonicalizing(
            array_values(array_unique(array_map('strtolower', array_values($asset['colors'])))),
            "{$asset['filename']} paints other colours than the manifest names",
        );
    }
});

it('uses reusable optimized svg assets for every svg variant', function () {
    foreach (logo_assets() as $asset) {
        if (! str_ends_with($asset['filename'], '.svg')) {
            continue;
        }

        $svg = (string) file_get_contents(ThemeContract::root() . '/' . $asset['path']);
        expect($svg)->toContain('<svg')->toContain('viewBox="' . $asset['view_box'] . '"')
            ->not->toMatch('/<(?:script|image|text)\b/i')
            // No external resource dependency. The SVG/xlink namespace literals
            // (www.w3.org/...) are identifiers, never fetched, so they are allowed;
            // any other http(s) URL is a forbidden external dependency.
            ->not->toMatch('#https?://(?!www\.w3\.org/)#i')
            // No font-text dependency: the wordmark ships as outlined vector paths,
            // never as live <text> bound to an installed/remote font.
            ->not->toMatch('/font-family|@font-face|\.woff/i');
    }
});

it('names a lockup "CoreX" and leaves the symbol and the favicon unnamed', function () {
    foreach (logo_assets() as $asset) {
        expect($asset['accessible_usage'] ?? null)->toBeIn(['decorative', 'named-image', 'linked-brand'])
            ->and($asset['client_site_default'] ?? null)->toBeFalse();

        if (! str_ends_with($asset['filename'], '.svg')) {
            expect($asset['accessible_usage'])->toBe('decorative');

            continue;
        }

        $svg = (string) file_get_contents(ThemeContract::root() . '/' . $asset['path']);

        if ($asset['accessible_usage'] === 'decorative') {
            expect($svg)->toContain('aria-hidden="true"')->not->toContain('<title')->not->toContain('aria-label');
        } else {
            expect($svg)->toContain('role="img"')->toContain('aria-label="CoreX"')->toContain('<title>CoreX</title>');
        }
    }

    foreach (['symbol', 'symbol-light', 'symbol-dark', 'favicon'] as $variant) {
        expect(logo_assets()[$variant]['accessible_usage'])->toBe('decorative');
    }

    foreach (['lockup', 'lockup-light', 'lockup-dark', 'stacked-light', 'stacked-dark', 'knockout', 'wordmark'] as $variant) {
        expect(logo_assets()[$variant]['accessible_usage'])->toBe('named-image');
    }
});

it('draws "Core" in the neutral colour and only the "x" and the centre in brass (FR-018)', function () {
    $assets = logo_assets();

    // The files drawn inline: the neutral is the text colour around them.
    foreach (['lockup' => '#c9a25e', 'contrast' => '#ad8643'] as $variant => $brass) {
        $parts = logo_parts($assets[$variant]['path']);

        expect($parts['core'])->toBe('currentcolor', "{$variant}: \"Core\" is the text colour")
            ->and($parts['x'])->toBe($brass)
            ->and($parts['centre'])->toBe($brass)
            ->and(array_unique($parts['corners']))->toBe(['currentcolor']);
    }

    // The files with fixed colours, for each ground.
    foreach (['lockup', 'stacked'] as $shape) {
        foreach (LOGO_INK as $ground => $ink) {
            $variant = "{$shape}-{$ground}";
            $parts = logo_parts($assets[$variant]['path']);

            expect($parts['core'])->toBe($ink['neutral'], "{$variant}: \"Core\" is neutral")
                ->and($parts['x'])->toBe($ink['brass'], "{$variant}: the \"x\" is brass")
                ->and($parts['centre'])->toBe($ink['brass'])
                ->and(array_unique($parts['corners']))->toBe([$ink['neutral']])
                ->and($assets[$variant]['colors']['neutral'])->toBe($ink['neutral'])
                ->and($assets[$variant]['colors']['brass'])->toBe($ink['brass']);
        }
    }
});

it('draws the horizontal lockups with the divider the approved artboard draws (FR-019)', function () {
    $assets = logo_assets();

    // The artboard's divider: #c2c8d1 on a light ground, #343b47 on a dark one. A stacked lockup
    // has none.
    expect(logo_parts($assets['lockup-light']['path'])['divider'])->toBe('#c2c8d1')
        ->and(logo_parts($assets['lockup-dark']['path'])['divider'])->toBe('#343b47')
        ->and(logo_parts($assets['stacked-light']['path'])['divider'])->toBeNull()
        ->and(logo_parts($assets['stacked-dark']['path'])['divider'])->toBeNull()
        ->and($assets['lockup-light']['view_box'])->toBe($assets['lockup']['view_box'])
        ->and($assets['lockup-dark']['view_box'])->toBe($assets['lockup']['view_box']);
});

it('draws the symbol alone in fixed colours for each ground (FR-019)', function () {
    foreach (LOGO_INK as $ground => $ink) {
        $shapes = logo_shapes(logo_assets()["symbol-{$ground}"]['path']);
        $centre = array_values(array_filter($shapes, static fn (array $s): bool => $s['x'] === '18'));

        expect($shapes)->toHaveCount(5)
            ->and($centre[0]['fill'])->toBe($ink['brass'])
            ->and(array_values(array_unique(array_column($shapes, 'fill'))))
            ->toEqualCanonicalizing([$ink['neutral'], $ink['brass']]);
    }
});

it('draws the knockout in one ink, graphite, on a brass tile (FR-019)', function () {
    $knockout = logo_assets()['knockout'];
    $shapes = logo_shapes($knockout['path']);

    // The tile is the first shape (svgo writes it as a path); everything on it is graphite.
    expect($shapes[0]['fill'])->toBe('#c9a25e')
        ->and(array_values(array_unique(array_column(array_slice($shapes, 1), 'fill'))))->toBe(['#14151a'])
        ->and($knockout['colors'])->toBe(['tile' => '#c9a25e', 'ink' => '#14151a']);
});

it('draws the favicon as the symbol on a graphite tile, at 16, 32, 180 and 512 pixels (FR-021)', function () {
    $assets = logo_assets();
    $shapes = logo_shapes($assets['favicon']['path']);

    expect($shapes[0]['fill'])->toBe('#14151a')
        ->and($assets['favicon']['colors'])->toBe(['tile' => '#14151a', 'neutral' => '#f4f5f7', 'brass' => '#c9a25e']);

    foreach ([16, 32, 180, 512] as $size) {
        $png = $assets["favicon-{$size}"];
        $at = static fn (float $units): int => (int) floor($units / 48 * $size);
        // The 16 and 32 are the favicon's drawing; the 180 and 512 fill the square (phones round
        // the corners themselves) and hold the symbol a little smaller.
        [$offset, $scale] = $size >= 180 ? [8.4, 0.65] : [6, 0.75];

        expect(array_slice((array) getimagesize(ThemeContract::root() . '/' . $png['path']), 0, 2))->toBe([$size, $size])
            ->and($png['pixels'])->toBe([$size, $size])
            ->and($png['colors'])->toBe($assets['favicon']['colors']);

        // Each names what it is drawn from: the shipped favicon, or the square drawing, which is
        // the generator's and no file of the folder.
        if ($size >= 180) {
            expect($png['rendered_from'])->toContain('the square favicon drawing')
                ->not->toBe($assets['favicon']['path']);
        } else {
            expect($png['rendered_from'])->toBe($assets['favicon']['path']);
        }

        // A corner module, the centre module and the tile, each sampled inside.
        expect(logo_pixel($png['path'], $at($offset + 9 * $scale), $at($offset + 9 * $scale))[0])->toBe('#f4f5f7')
            ->and(logo_pixel($png['path'], $at($offset + 24 * $scale), $at($offset + 24 * $scale))[0])->toBe('#c9a25e')
            ->and(logo_pixel($png['path'], $at(24), $at($offset + 2 * $scale))[0])->toBe('#14151a');

        // The corner pixel: opaque graphite where the drawing fills the square, clear where the tile
        // is rounded.
        [$corner, $opacity] = logo_pixel($png['path'], 0, 0);

        if ($size >= 180) {
            expect($corner)->toBe('#14151a')->and($opacity)->toBe(1.0);
        } else {
            expect($opacity)->toBe(0.0);
        }
    }
});

it('keeps the theme\'s favicon copies equal to the logo folder\'s (FR-024, DECISIONS #637)', function () {
    $copies = 0;

    foreach (logo_assets() as $asset) {
        if (! str_starts_with($asset['variant'], 'favicon')) {
            expect($asset)->not->toHaveKey('theme_copy');

            continue;
        }

        $copy = ThemeContract::root() . '/' . $asset['theme_copy'];

        expect($asset['theme_copy'])->toBe('theme/assets/favicon/' . $asset['filename'])
            ->and($copy)->toBeFile()
            ->and(hash_file('sha256', $copy))->toBe($asset['sha256'], "{$asset['theme_copy']} differs from the logo folder's");
        $copies++;
    }

    // And the theme holds no favicon file the logo folder does not.
    expect($copies)->toBe(5)
        ->and(glob(ThemeContract::root() . '/theme/assets/favicon/*') ?: [])->toHaveCount(5);
});

it('removes corex-mark.svg, and no generator writes it (FR-023)', function () {
    expect(ThemeContract::root() . '/' . LOGO_FOLDER . '/corex-mark.svg')->not->toBeFile();

    foreach (glob(ThemeContract::root() . '/scripts/*') ?: [] as $script) {
        if (is_file($script)) {
            expect((string) file_get_contents($script))->not->toContain('corex-mark.svg', basename($script) . ' names corex-mark.svg');
        }
    }
});

it('signs a PDF with the approved lockup, as the image the manifest records', function () {
    $manifest = logo_manifest();
    $print = $manifest['print'];
    $image = ThemeContract::root() . '/' . $print['path'];

    // The image is rendered from an approved asset, never drawn again: a change to either is a
    // change to the other, made with the generator the manifest names.
    expect(array_column($manifest['assets'], 'path'))->toContain($print['rendered_from'])
        ->and(ThemeContract::root() . '/' . $print['generator'])->toBeFile()
        ->and($image)->toBeFile()
        ->and(hash_file('sha256', $image))->toBe($print['sha256'])
        ->and(array_slice((array) getimagesize($image), 0, 2))->toBe($print['pixels']);

    // And it is the image a PDF is signed with.
    expect((string) file_get_contents(ThemeContract::root() . '/plugins/corex-config/src/ConfigServiceProvider.php'))
        ->toContain("'/assets/brand/" . $print['filename'] . "'");
});
