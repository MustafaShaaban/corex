<?php

/**
 * The brand is written once and every copy holds it (spec 121, US-BRD-02, DECISIONS #460, #461;
 * spec 134, US-BRD-01, DECISIONS #634).
 *
 * `design/tokens/brand-contract.json` lists each colour, shadow, radius and motion value with the
 * copies that hold it, and the theme's palette and custom colours by mode. A copy that holds another
 * value fails here, with the token's name and the copy's, so the admin, the emails and the theme
 * cannot drift apart again.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Email\Design\EmailBrand;
use Corex\Email\Design\EmailPalette;
use Corex\Tests\Support\ThemeContract;

const BRAND_CONTRACT_ADMIN_CSS = 'plugins/corex-core/assets/css/corex-admin-tokens.css';

/** @return array<string,mixed> */
function brand_contract(): array
{
    return ThemeContract::json('design/tokens/brand-contract.json');
}

/** One value as the tests compare it: lower case, single spaces, `#fff` written out. */
function brand_contract_normal(string $value): string
{
    $value = strtolower(trim((string) preg_replace('/\s+/', ' ', $value)));

    return (string) preg_replace('/#([0-9a-f])([0-9a-f])([0-9a-f])\b/', '#$1$1$2$2$3$3', $value);
}

/**
 * The custom properties one rule of the admin token stylesheet declares.
 *
 * @return array<string,string>
 */
function brand_contract_declarations(string $block): array
{
    preg_match_all('/(--corex-admin-[a-z0-9-]+)\s*:\s*([^;]+);/', $block, $matches, PREG_SET_ORDER);

    return array_column($matches, 2, 1);
}

/**
 * The admin's dark set, its System-light block and its explicit Light block.
 *
 * @return array{dark: array<string,string>, system: array<string,string>, light: array<string,string>}
 */
function brand_contract_admin(): array
{
    $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents(ThemeContract::root() . '/' . BRAND_CONTRACT_ADMIN_CSS));
    $rule = static function (string $opening) use ($css): string {
        $start = strpos($css, $opening);
        expect($start)->not->toBeFalse("The admin token stylesheet has no rule starting {$opening}");
        $body = substr($css, (int) $start + strlen($opening));

        return substr($body, 0, (int) strpos($body, '}'));
    };

    return [
        'dark' => brand_contract_declarations($rule(".corex-admin,\nbody.login.corex-login,\nbody.corex-admin-screen {")),
        'system' => brand_contract_declarations($rule("body.corex-admin-screen:not(.corex-appearance-light):not(.corex-appearance-dark) {")),
        'light' => brand_contract_declarations($rule("body.corex-admin-screen.corex-appearance-light {")),
    ];
}

/**
 * A custom property's value in one mode, with `var()` references to other admin tokens resolved.
 *
 * @param array<string,string> $tokens
 */
function brand_contract_resolve(array $tokens, string $property): ?string
{
    $value = $tokens[$property] ?? null;

    for ($depth = 0; $value !== null && $depth < 5 && preg_match('/^var\((--corex-admin-[a-z0-9-]+)\)$/', trim($value), $match); $depth++) {
        $value = $tokens[$match[1]] ?? null;
    }

    return $value === null ? null : brand_contract_normal($value);
}

/** A rem or px length in pixels, for the email constants written in pixels. */
function brand_contract_pixels(string $length): float
{
    return str_ends_with($length, 'rem') ? (float) $length * 16 : (float) $length;
}

it('holds every admin colour and shadow at the contract value, in dark and in light', function () {
    $contract = brand_contract();
    $admin = brand_contract_admin();
    $modes = ['dark' => $admin['dark'], 'light' => array_merge($admin['dark'], $admin['light'])];
    $differences = [];

    foreach (['colors', 'shadows'] as $group) {
        foreach ($contract[$group] as $token => $entry) {
            foreach ($modes as $mode => $tokens) {
                $actual = brand_contract_resolve($tokens, $entry['admin']);
                if ($actual !== brand_contract_normal($entry[$mode])) {
                    $differences[] = sprintf('%s.%s (%s): the admin\'s %s is %s, the contract %s', $group, $token, $mode, $entry['admin'], $actual ?? 'missing', $entry[$mode]);
                }
            }
        }
    }

    expect($differences)->toBe([]);
});

it('gives Light and System on a light computer the same admin', function () {
    $admin = brand_contract_admin();
    $system = array_map('brand_contract_normal', $admin['system']);
    $light = array_map('brand_contract_normal', $admin['light']);
    ksort($system);
    ksort($light);

    // Shadows, overlay, login card, login field and backdrop included (TC-BRD-02-03, TC-BRD-02-04).
    expect($light)->toHaveKeys([
        '--corex-admin-shadow-sm', '--corex-admin-shadow-md', '--corex-admin-shadow-lg', '--corex-admin-overlay',
        '--corex-admin-login-card', '--corex-admin-login-field', '--corex-admin-login-backdrop',
    ])->and($system)->toBe($light);
});

it('holds every radius and motion value in the admin and the theme', function () {
    $contract = brand_contract();
    $admin = brand_contract_admin()['dark'];
    $theme = ThemeContract::json('theme/theme.json');
    $differences = [];

    foreach (['radius', 'motion'] as $group) {
        foreach ($contract[$group] as $token => $entry) {
            $expected = brand_contract_normal($entry['value']);
            $inAdmin = brand_contract_resolve($admin, $entry['admin']);
            $inTheme = brand_contract_path($theme, $entry['theme']);

            if ($inAdmin !== $expected) {
                $differences[] = sprintf('%s.%s: the admin\'s %s is %s, the contract %s', $group, $token, $entry['admin'], $inAdmin ?? 'missing', $entry['value']);
            }
            if (! is_string($inTheme) || brand_contract_normal($inTheme) !== $expected) {
                $differences[] = sprintf('%s.%s: theme.json %s is %s, the contract %s', $group, $token, $entry['theme'], is_string($inTheme) ? $inTheme : 'missing', $entry['value']);
            }
        }
    }

    expect($differences)->toBe([]);
});

it('holds the email palette and the email radius at the contract value', function () {
    $contract = brand_contract();
    $palettes = ['dark' => EmailPalette::dark(), 'light' => EmailPalette::light()];
    $differences = [];

    foreach ($contract['colors'] as $token => $entry) {
        if (! isset($entry['email'])) {
            continue;
        }
        foreach ($palettes as $mode => $palette) {
            $actual = brand_contract_normal($palette->{$entry['email']});
            if ($actual !== brand_contract_normal($entry[$mode])) {
                $differences[] = sprintf('colors.%s (%s): EmailPalette %s is %s, the contract %s', $token, $mode, $entry['email'], $actual, $entry[$mode]);
            }
        }
    }

    expect($differences)->toBe([])
        ->and(brand_contract_pixels(EmailBrand::RADIUS))->toBe(brand_contract_pixels($contract['radius']['md']['value']));
});

/**
 * A colour as numbers, so `rgba(15, 17, 22, 0.54)` and `rgb(15 17 22 / 54%)` compare equal.
 * Returns the value as it is when it is not a hex, `rgb()` or `rgba()` colour.
 */
function brand_contract_colour(string $value): string
{
    $value = brand_contract_normal($value);

    if (preg_match('/^#([0-9a-f]{2})([0-9a-f]{2})([0-9a-f]{2})$/', $value, $hex) === 1) {
        return sprintf('%d,%d,%d,1', hexdec($hex[1]), hexdec($hex[2]), hexdec($hex[3]));
    }

    if (preg_match('/^rgba?\(\s*(\d+)[\s,]+(\d+)[\s,]+(\d+)\s*(?:[,\/]\s*([\d.]+)(%?))?\s*\)$/', $value, $rgb) === 1) {
        $alpha = isset($rgb[4]) && $rgb[4] !== '' ? (float) $rgb[4] / (($rgb[5] ?? '') === '%' ? 100 : 1) : 1.0;

        return sprintf('%d,%d,%d,%s', $rgb[1], $rgb[2], $rgb[3], rtrim(rtrim(sprintf('%.4f', $alpha), '0'), '.'));
    }

    return $value;
}

/**
 * The theme's colours and shadows in one mode, keyed as the contract keys them: `palette.<slug>`,
 * `custom.<key>` (settings.custom.color) and `shadows.<slug>`.
 *
 * @param array<string,mixed> $json theme.json or a style variation
 * @return array<string,string>
 */
function brand_contract_theme_values(array $json): array
{
    $values = [];

    foreach ($json['settings']['color']['palette'] ?? [] as $entry) {
        $values['palette.' . $entry['slug']] = (string) $entry['color'];
    }
    foreach ($json['settings']['custom']['color'] ?? [] as $key => $value) {
        $values['custom.' . $key] = (string) $value;
    }
    foreach ($json['settings']['shadow']['presets'] ?? [] as $entry) {
        $values['shadows.' . $entry['slug']] = (string) $entry['shadow'];
    }

    return $values;
}

/**
 * What the contract says the theme holds, by mode, keyed as {@see brand_contract_theme_values()}.
 *
 * @param array<string,mixed> $contract
 * @return array{light: array<string,string>, dark: array<string,string>}
 */
function brand_contract_theme_expected(array $contract): array
{
    $expected = ['light' => [], 'dark' => []];

    foreach (['palette', 'custom'] as $group) {
        foreach ($contract['theme'][$group] as $token => $entry) {
            foreach (['light', 'dark'] as $mode) {
                $expected[$mode][$group . '.' . $token] = $entry[$mode];
            }
        }
    }
    foreach ($contract['shadows'] as $entry) {
        foreach (['light', 'dark'] as $mode) {
            $expected[$mode]['shadows.' . $entry['theme']] = $entry[$mode];
        }
    }

    return $expected;
}

/**
 * Each way the theme's files differ from the contract, one line each, naming the token, the mode
 * and the file (FR-004).
 *
 * @param array<string,mixed> $contract
 * @param array<string,array<string,mixed>> $files mode => [file => path, json => decoded file]
 * @return list<string>
 */
function brand_contract_theme_differences(array $contract, array $files): array
{
    $expected = brand_contract_theme_expected($contract);
    $differences = [];

    foreach ($files as $mode => $file) {
        $actual = brand_contract_theme_values($file['json']);

        foreach ($expected[$mode] as $token => $value) {
            if (! isset($actual[$token])) {
                $differences[] = sprintf('%s (%s): %s does not define it, the contract %s', $token, $mode, $file['file'], $value);
            } elseif (brand_contract_normal($actual[$token]) !== brand_contract_normal($value)) {
                $differences[] = sprintf('%s (%s): %s holds %s, the contract %s', $token, $mode, $file['file'], $actual[$token], $value);
            }
        }
        foreach (array_diff_key($actual, $expected[$mode]) as $token => $value) {
            $differences[] = sprintf('%s (%s): %s holds %s, which the contract does not list', $token, $mode, $file['file'], $value);
        }
    }

    return $differences;
}

/** @return array<string,array<string,mixed>> */
function brand_contract_theme_files(): array
{
    return [
        'light' => ['file' => 'theme/theme.json', 'json' => ThemeContract::json('theme/theme.json')],
        'dark' => ['file' => 'theme/styles/dark.json', 'json' => ThemeContract::json('theme/styles/dark.json')],
    ];
}

// Spec 134, FR-001 to FR-004 (DECISIONS #634): the theme's palette, custom colours and shadows are bound.
it('holds the theme\'s palette, custom colours and shadows at the contract value, light and dark', function () {
    expect(brand_contract_theme_differences(brand_contract(), brand_contract_theme_files()))->toBe([]);
});

it('names the token, the mode and the file when the theme holds another value', function () {
    $files = brand_contract_theme_files();
    $files['dark']['json']['settings']['custom']['color']['bandInk'] = '#ffffff';
    $files['light']['json']['settings']['shadow']['presets'][0]['shadow'] = '0 1px 2px black';
    unset($files['light']['json']['settings']['custom']['color']['onAccent']);

    $differences = brand_contract_theme_differences(brand_contract(), $files);

    expect($differences)->toHaveCount(3)
        ->and($differences)->toContain('custom.bandInk (dark): theme/styles/dark.json holds #ffffff, the contract #f4f5f7')
        ->and($differences)->toContain('shadows.sm (light): theme/theme.json holds 0 1px 2px black, the contract 0 1px 2px rgb(20 21 26 / 8%)')
        ->and($differences)->toContain('custom.onAccent (light): theme/theme.json does not define it, the contract #14151a');
});

it('lists every slug, custom colour and shadow the theme defines, and the ones it must keep', function () {
    $contract = brand_contract();
    $light = brand_contract_theme_values(brand_contract_theme_files()['light']['json']);

    // Every slug keeps its name, so a brand.json and a client theme keep working (FR-001).
    expect(array_keys($contract['theme']['palette']))->toContain(
        'primary', 'primary-dark', 'accent', 'accent-dark', 'surface', 'surface-alt', 'surface-raised', 'surface-strong',
        'border', 'ink', 'ink-soft', 'success', 'warning', 'error', 'info', 'inverse', 'overlay', 'selection',
        'selection-text', 'background', 'foreground', 'danger', 'corex-danger', 'corex-info', 'corex-primary',
        'corex-success', 'corex-warning', 'white',
    )
        // The kit's custom colour tokens (FR-002).
        ->and(array_keys($contract['theme']['custom']))->toEqualCanonicalizing([
            'onAccent', 'accentHover', 'accentSubtle', 'successSoft', 'warningSoft', 'errorSoft', 'infoSoft',
            'band', 'bandRaised', 'bandInk', 'bandInkSoft', 'bandLine', 'bandBorder', 'bandAccent', 'bandAccentHover',
            'bandAccentSubtle', 'bandLink',
        ])
        ->and(array_column($contract['shadows'], 'theme'))->toBe(['sm', 'md', 'lg'])
        ->and(count($light))->toBe(count($contract['theme']['palette']) + count($contract['theme']['custom']) + 3);
});

it('gives each compatibility slug the value of the role it follows, in both modes', function () {
    $palette = brand_contract()['theme']['palette'];
    $differ = [];

    foreach ($palette as $slug => $entry) {
        if (! isset($entry['follows'])) {
            continue;
        }
        foreach (['light', 'dark'] as $mode) {
            if (brand_contract_normal($entry[$mode]) !== brand_contract_normal($palette[$entry['follows']][$mode])) {
                $differ[] = sprintf('palette.%s (%s) is %s, its role %s is %s', $slug, $mode, $entry[$mode], $entry['follows'], $palette[$entry['follows']][$mode]);
            }
        }
    }

    expect($differ)->toBe([]);
});

// Spec 134, FR-005: a theme colour is a brand colour, or the contract says why it is not.
it('draws every theme colour from the brand, apart from the exceptions the contract gives a reason for', function () {
    $contract = brand_contract();
    $brand = [];
    foreach ($contract['colors'] as $entry) {
        $brand[] = brand_contract_colour($entry['light']);
        $brand[] = brand_contract_colour($entry['dark']);
    }
    $outside = [];
    $stale = [];

    foreach (['palette', 'custom'] as $group) {
        foreach ($contract['theme'][$group] as $token => $entry) {
            foreach (['light', 'dark'] as $mode) {
                // A compatibility slug takes the reason of the role it follows.
                $exception = $entry['exception'][$mode] ?? $contract['theme'][$group][$entry['follows'] ?? '']['exception'][$mode] ?? null;
                $reason = trim((string) $exception);
                $isBrand = in_array(brand_contract_colour($entry[$mode]), $brand, true);

                if (! $isBrand && $reason === '') {
                    $outside[] = sprintf('theme.%s.%s (%s): %s is not a brand colour', $group, $token, $mode, $entry[$mode]);
                }
                if ($isBrand && $exception !== null) {
                    $stale[] = sprintf('theme.%s.%s (%s): %s is a brand colour, so it needs no exception', $group, $token, $mode, $entry[$mode]);
                }
            }
        }
    }

    expect($outside)->toBe([])
        ->and($stale)->toBe([])
        // One today: `success` in light, because the admin's green is 4.04:1 as text on white.
        ->and($contract['theme']['palette']['success']['exception'] ?? [])->toHaveKey('light');
});

it('reads a colour the same in each notation', function () {
    expect(brand_contract_colour('rgba(15, 17, 22, 0.54)'))->toBe(brand_contract_colour('rgb(15 17 22 / 54%)'))
        ->and(brand_contract_colour('#FFF'))->toBe(brand_contract_colour('rgb(255, 255, 255)'))
        ->and(brand_contract_colour('#ad8643'))->not->toBe(brand_contract_colour('rgba(173, 134, 67, 0.12)'));
});

/**
 * A value at a dotted path, or null.
 *
 * @param array<string,mixed> $data
 */
function brand_contract_path(array $data, string $path): mixed
{
    foreach (explode('.', $path) as $key) {
        if (! is_array($data) || ! array_key_exists($key, $data)) {
            return null;
        }
        $data = $data[$key];
    }

    return $data;
}
