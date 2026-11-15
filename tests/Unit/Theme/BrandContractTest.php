<?php

/**
 * The brand is written once and every copy holds it (spec 121, US-BRD-02, DECISIONS #460, #461).
 *
 * `design/tokens/brand-contract.json` lists each colour, shadow, radius and motion value with the
 * copies that hold it. A copy that holds another value fails here, with the token's name and the
 * copy's, so the admin, the emails and the theme cannot drift apart again.
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
