<?php

/**
 * The navy-era palette is gone from everything CoreX ships (spec 134, US-BRD-01, FR-009, SC-004;
 * spec 121, FR-025, TC-DOC-07-04, SC-007; DECISIONS #633).
 *
 * The values are held here as numbers and written out when the files are searched, so this test
 * is not itself a file that names them. Only the decision record, the changelog and the specs
 * written before the theme moved keep them, as history. Spec 057's generated inventories are
 * searched: they are written from the theme.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;

/**
 * The navy-era colours: the light palette's navy and its cyan accents, the dark variation's navy
 * surfaces and its sky blues, and the navy the shadows and the overlay were drawn in.
 *
 * @return list<array{int,int,int}>
 */
function navy_era_colours(): array
{
    return [
        [0x0B, 0x1F, 0x3B], [0x06, 0x12, 0x1F], [0x10, 0x2A, 0x4C], [0x18, 0x3B, 0x61],
        [0x00, 0x7A, 0x9E], [0x00, 0x60, 0x80], [0x00, 0xC2, 0xFF], [0x7D, 0xD3, 0xFC],
        [0x38, 0xBD, 0xF8], [0x07, 0x59, 0x85], [0xBF, 0xEF, 0xFF],
    ];
}

/** One pattern that finds any of them, as hex or as `rgb()`/`rgba()`, in any case. */
function navy_era_pattern(): string
{
    $forms = [];

    foreach (navy_era_colours() as [$red, $green, $blue]) {
        $forms[] = sprintf('(?<![0-9a-f])%02x%02x%02x(?![0-9a-f])', $red, $green, $blue);
        $forms[] = sprintf('rgba?\(\s*%d[\s,]+%d[\s,]+%d\b', $red, $green, $blue);
    }

    return '/' . implode('|', $forms) . '/i';
}

/** A path that keeps the old values as history. */
function navy_era_history(string $path): bool
{
    return in_array($path, ['DECISIONS.md', 'CHANGELOG.md'], true)
        || (str_starts_with($path, 'specs/') && ! str_starts_with($path, 'specs/057-brand-tokens-logo-system/inventories/'));
}

/**
 * A file the search passes over: history, a client's own site (`sites/`, spec 102), a file gone from
 * the disk, one over 2 MB, and a binary one (a NUL in its first 8000 bytes, as git decides). A client
 * repository holds gigabytes of its own media, which this test must never read.
 */
function navy_era_skipped(string $path, string $root): bool
{
    $file = $root . '/' . $path;

    if (navy_era_history($path) || str_starts_with($path, 'sites/') || ! is_file($file) || filesize($file) > 2 * 1024 * 1024) {
        return true;
    }

    $handle = fopen($file, 'rb');
    $head = $handle === false ? '' : (string) fread($handle, 8000);
    if ($handle !== false) {
        fclose($handle);
    }

    return $handle === false || str_contains($head, "\0");
}

/**
 * The files git tracks or would track, less the ones {@see navy_era_skipped()} passes over.
 *
 * @return list<string>
 */
function navy_era_files(): array
{
    $root = ThemeContract::root();
    $listed = (string) shell_exec(sprintf('git -C %s ls-files -z --cached --others --exclude-standard', escapeshellarg($root)));

    return array_values(array_filter(
        explode("\0", $listed),
        static fn (string $path): bool => $path !== '' && ! navy_era_skipped($path, $root),
    ));
}

// A client repository holds its own site and its media: the search reads only CoreX's text files.
it('passes over a client\'s site, a binary file and a file over 2 MB', function () {
    $root = sys_get_temp_dir() . '/corex-navy-era-' . bin2hex(random_bytes(4));
    mkdir($root . '/sites/acme', 0777, true);
    file_put_contents($root . '/sites/acme/style.css', 'a {}');
    file_put_contents($root . '/media.bin', "GIF89a\0\0\1");
    file_put_contents($root . '/large.txt', str_repeat('a', 2 * 1024 * 1024 + 1));
    file_put_contents($root . '/theme.json', '{}');

    try {
        expect(navy_era_skipped('sites/acme/style.css', $root))->toBeTrue()
            ->and(navy_era_skipped('media.bin', $root))->toBeTrue()
            ->and(navy_era_skipped('large.txt', $root))->toBeTrue()
            ->and(navy_era_skipped('missing.css', $root))->toBeTrue()
            ->and(navy_era_skipped('DECISIONS.md', $root))->toBeTrue()
            ->and(navy_era_skipped('theme.json', $root))->toBeFalse();
    } finally {
        foreach (['sites/acme/style.css', 'media.bin', 'large.txt', 'theme.json'] as $file) {
            unlink($root . '/' . $file);
        }
        rmdir($root . '/sites/acme');
        rmdir($root . '/sites');
        rmdir($root);
    }
});

it('finds the navy-era colours in every way a file can write them', function () {
    $pattern = navy_era_pattern();

    expect(preg_match($pattern, 'color: #' . sprintf('%02X%02X%02X', 0x0B, 0x1F, 0x3B) . ';'))->toBe(1)
        ->and(preg_match($pattern, sprintf('box-shadow: 0 1px 2px rgba(%d, %d, %d, 0.08)', 0x0B, 0x1F, 0x3B)))->toBe(1)
        ->and(preg_match($pattern, sprintf("toHaveCSS('background-color', 'rgb(%d, %d, %d)')", 0x10, 0x2A, 0x4C)))->toBe(1)
        // A commit hash or a longer hex value that holds the same letters is not a colour.
        ->and(preg_match($pattern, sprintf('a1%02x%02x%02x9f', 0x0B, 0x1F, 0x3B)))->toBe(0)
        ->and(preg_match($pattern, 'color: #14151a;'))->toBe(0);
});

it('ships no file, test, fixture or docs page that names a navy-era colour', function () {
    $files = navy_era_files();
    $pattern = navy_era_pattern();
    $found = [];

    foreach ($files as $path) {
        $lines = explode("\n", (string) file_get_contents(ThemeContract::root() . '/' . $path));
        foreach ($lines as $number => $line) {
            if (preg_match($pattern, $line, $match) === 1) {
                $found[] = sprintf('%s:%d names %s', $path, $number + 1, $match[0]);
            }
        }
    }

    expect($files)->toContain('theme/theme.json', 'theme/styles/dark.json', 'plugins/corex-core/languages/corex-ar.po')
        ->and($found)->toBe([]);
});
