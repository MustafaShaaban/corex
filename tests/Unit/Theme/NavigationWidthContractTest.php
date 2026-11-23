<?php

/**
 * Spec 137, slice 1 (US-NAV-11; FR-001, FR-003, FR-007 (a) and (b); DECISIONS #692): one width.
 *
 * The CoreX theme's navigation breakpoint token, the width the collapse stylesheet is written
 * with and the default the breakpoint writer replaces are one value; and no other CoreX
 * stylesheet or script decides the navigation's form by a width of its own.
 *
 * @package Corex\Tests\Unit\Theme
 */

declare(strict_types=1);

use Corex\Tests\Support\ThemeContract;
use Corex\Theme\NavigationBreakpoint;
use Corex\Theme\NavigationCollapseStyle;

/** What marks a rule or a script as one of the navigation's. */
const COREX_NAV_SELECTORS = '/corex-header|corex-mega|corex-mobile-menu|wp-block-navigation/';

/** A width feature in a media condition, in either syntax. */
const COREX_WIDTH_FEATURE = '/(?<![-\w])(?:min-|max-)?width\b/';

/** CSS with its comments taken out. */
function corexNavWithoutComments(string $css): string
{
    return (string) preg_replace('#/\*.*?\*/#s', '', $css);
}

/**
 * Every `@media` rule of a stylesheet: its condition and what its braces hold.
 *
 * @return list<array{condition: string, body: string}>
 */
function corexNavMediaRules(string $css): array
{
    $css = corexNavWithoutComments($css);
    $rules = [];
    $offset = 0;

    while (preg_match('/@media\b([^{]*)\{/', $css, $match, PREG_OFFSET_CAPTURE, $offset) === 1) {
        $start = $match[0][1] + strlen($match[0][0]);
        $depth = 1;
        $at = $start;

        while ($depth > 0 && $at < strlen($css)) {
            $depth += match ($css[$at]) {
                '{' => 1,
                '}' => -1,
                default => 0,
            };
            $at++;
        }

        $rules[] = ['condition' => trim($match[1][0]), 'body' => substr($css, $start, $at - $start - 1)];
        $offset = $start;
    }

    return $rules;
}

/**
 * The CoreX source files with these extensions, as the token contracts read them.
 *
 * @return list<string>
 */
function corexNavSources(string $extensions): array
{
    return array_values(array_filter(
        ThemeContract::sourceFiles(),
        static fn (string $relative): bool => preg_match('/\.(?:' . $extensions . ')$/', $relative) === 1
            && ! str_ends_with($relative, '.test.js'),
    ));
}

function corexNavToken(): mixed
{
    return ThemeContract::json('theme/theme.json')['settings']['custom']['nav']['breakpoint'] ?? null;
}

function corexNavCollapseCss(): string
{
    $path = ThemeContract::root() . '/theme/' . NavigationCollapseStyle::RELATIVE_PATH;
    expect($path)->toBeFile();

    return (string) file_get_contents($path);
}

it('keeps the CoreX theme\'s token at the width the writer treats as the default (FR-001, FR-007 a)', function () {
    expect(corexNavToken())->toBe('782px')
        ->and(NavigationBreakpoint::DEFAULT)->toBe(corexNavToken());
});

it('writes the collapse stylesheet with the token\'s width, and with no other width but WordPress\'s own (FR-003, FR-007 a)', function () {
    $token = (string) corexNavToken();
    $rules = corexNavMediaRules(corexNavCollapseCss());
    $conditions = array_column($rules, 'condition');

    // From the token up the header is in its desktop form; between WordPress's 600px and the token
    // its menu is folded behind the button. Range syntax, so neither side is one pixel off.
    expect($conditions)->toContain("(width >= {$token})")
        ->and($conditions)->toContain('(' . NavigationBreakpoint::FLOOR . " <= width < {$token})");

    foreach ($rules as $rule) {
        if (preg_match(COREX_WIDTH_FEATURE, $rule['condition']) !== 1) {
            continue;
        }

        preg_match_all('/\d*\.?\d+[a-z%]+/', $rule['condition'], $lengths);
        expect(array_diff($lengths[0], [$token, NavigationBreakpoint::FLOOR]))
            ->toBe([], "the condition {$rule['condition']} names a width that is neither the token nor 600px");
    }
});

it('names the token\'s width only in the conditions, so the writer replaces nothing else (FR-004)', function () {
    $css = corexNavCollapseCss();
    $token = (string) corexNavToken();
    $inConditions = 0;

    foreach (corexNavMediaRules($css) as $rule) {
        $inConditions += substr_count($rule['condition'], $token);
    }

    // Comments included: the inline copy is the file with the token's width replaced, word for word.
    expect(substr_count($css, $token))->toBe($inConditions);

    // Outside its conditions the stylesheet reads tokens: no length of its own in any declaration.
    $declarations = (string) preg_replace('/@media\b[^{]*\{/', '{', corexNavWithoutComments($css));
    preg_match_all('/(?<![\w-])\d*\.?\d+(?:px|r?em|vw|vh|%)\b/', $declarations, $lengths);
    expect($lengths[0])->toBe([]);
});

it('lets no other CoreX stylesheet decide a navigation\'s form by a width (FR-003, FR-007 b)', function () {
    $collapse = 'theme/' . NavigationCollapseStyle::RELATIVE_PATH;
    $offenders = [];

    foreach (corexNavSources('css|scss') as $relative) {
        if ($relative === $collapse) {
            continue;
        }

        $css = (string) file_get_contents(ThemeContract::root() . '/' . $relative);

        foreach (corexNavMediaRules($css) as $rule) {
            $byWidth = preg_match(COREX_WIDTH_FEATURE, $rule['condition']) === 1;
            // The theme's own stylesheets are its navigation's: none of them names a width at all.
            $isNavigation = str_starts_with($relative, 'theme/') || preg_match(COREX_NAV_SELECTORS, $rule['body']) === 1;

            if ($byWidth && $isNavigation) {
                $offenders[] = "{$relative}: @media {$rule['condition']}";
            }
        }
    }

    expect($offenders)->toBe([]);
});

it('lets no CoreX script or theme file that handles a navigation read the window\'s width (FR-003, FR-007 b)', function () {
    $offenders = [];
    $readsWidth = '/matchMedia\s*\(|\binnerWidth\b|\bouterWidth\b|\bscreen\.width\b|@media\b[^{]*(?:min-|max-)?width/';

    foreach (corexNavSources('js|php|html') as $relative) {
        $source = (string) file_get_contents(ThemeContract::root() . '/' . $relative);
        $isNavigation = str_starts_with($relative, 'theme/') || preg_match(COREX_NAV_SELECTORS, $source) === 1;

        if ($isNavigation && preg_match($readsWidth, $source) === 1) {
            $offenders[] = $relative;
        }
    }

    // The theme's parts and templates are HTML, which the token contracts do not list.
    foreach (glob(ThemeContract::root() . '/theme/{parts,templates}/*.html', GLOB_BRACE) ?: [] as $file) {
        if (preg_match($readsWidth, (string) file_get_contents($file)) === 1) {
            $offenders[] = substr(str_replace('\\', '/', $file), strlen(str_replace('\\', '/', ThemeContract::root())) + 1);
        }
    }

    expect($offenders)->toBe([]);
});
