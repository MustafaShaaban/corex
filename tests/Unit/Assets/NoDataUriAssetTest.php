<?php

/**
 * Asset-honesty guard (Spec 060 / Blocker 18): shipped CoreX styles, scripts, and
 * markup must reference real asset files (SVG/PNG/WebP) — never embed design artwork
 * as `data:` URIs or base64. Tiny semantic UI glyphs are coded as inline `<svg>`
 * elements, which this guard does not touch; it only forbids `data:` image payloads.
 *
 * @package Corex\Tests\Unit\Assets
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

/**
 * @return list<string>
 */
function corexShippedAssetFiles(): array
{
    $roots = ['plugins', 'addons', 'theme'];
    $extensions = ['css', 'js', 'php'];
    $skip = ['/node_modules/', '/vendor/', '/.git/'];
    $files = [];

    foreach ($roots as $relativeRoot) {
        $root = ThemeContract::root() . '/' . $relativeRoot;

        if (! is_dir($root)) {
            continue;
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        );

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if (! $file->isFile()) {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());

            foreach ($skip as $needle) {
                if (str_contains($path, $needle)) {
                    continue 2;
                }
            }

            if (in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = $path;
            }
        }
    }

    return $files;
}

it('embeds no design artwork as data: URIs in shipped assets', function () {
    $offenders = [];

    foreach (corexShippedAssetFiles() as $path) {
        $contents = (string) file_get_contents($path);

        if (preg_match('#data:image/[a-z.+-]+(;base64)?,#i', $contents) === 1) {
            $offenders[] = str_replace(ThemeContract::root() . '/', '', $path);
        }
    }

    expect($offenders)->toBe([], 'Design assets must be real files, not data: URIs: ' . implode(', ', $offenders));
});

it('finds the asset files it is supposed to scan', function () {
    // Guards against a silently-empty scan (a passing test that checks nothing).
    expect(count(corexShippedAssetFiles()))->toBeGreaterThan(50);
});
