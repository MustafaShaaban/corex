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
 * The files git tracks or would track, with the ones that keep history and the images left out.
 *
 * @return list<string>
 */
function navy_era_files(): array
{
    $listed = (string) shell_exec(sprintf('git -C %s ls-files -z --cached --others --exclude-standard', escapeshellarg(ThemeContract::root())));
    $files = [];

    foreach (array_filter(explode("\0", $listed)) as $path) {
        if (navy_era_history($path)
            || preg_match('/\.(?:png|jpe?g|gif|webp|ico|woff2?|ttf|zip|pdf)$/i', $path)
            || ! is_file(ThemeContract::root() . '/' . $path)
        ) {
            continue;
        }
        $files[] = $path;
    }

    return $files;
}

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
