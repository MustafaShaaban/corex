<?php

/**
 * Unit tests for the reader of the CoreX theme's tokens (spec 135, slice 3, G-006).
 *
 * A generated client theme is a child of the CoreX theme, and a preset list a child declares
 * replaces the parent's whole list (DECISIONS #660). So the child's `theme.json` is built from the
 * CoreX theme's own file at generation. Every expectation here is read from the repository's
 * `theme/theme.json` (FR-040), so the tests hold on any line whose theme has other tokens.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Site\ParentThemeTokens;
use Corex\Cli\Site\ParentThemeUnavailable;

function parentThemeJsonPath(): string
{
    return dirname(__DIR__, 3) . '/theme/theme.json';
}

/** @return array<string,mixed> The repository's CoreX theme.json, decoded. */
function parentThemeJson(): array
{
    return json_decode((string) file_get_contents(parentThemeJsonPath()), true, 512, JSON_THROW_ON_ERROR);
}

function parentThemeScratch(): string
{
    $dir = sys_get_temp_dir() . '/corex_parent_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

/**
 * @param array<string,mixed> $parent
 *
 * @return array<string,mixed> The child theme.json built from it, decoded.
 */
function childBuiltFrom(array $parent): array
{
    $file = parentThemeScratch() . '/theme.json';
    file_put_contents($file, (string) json_encode($parent));

    return json_decode((new ParentThemeTokens([$file]))->childThemeJson(), true, 512, JSON_THROW_ON_ERROR);
}

it('FR-011: lists every preset list the CoreX theme declares, with its values, in its order', function () {
    $parent = parentThemeJson();
    $child  = json_decode((new ParentThemeTokens([parentThemeJsonPath()]))->childThemeJson(), true);
    $lists  = 0;

    foreach (ParentThemeTokens::PRESET_LISTS as [$group, $list]) {
        if (! isset($parent['settings'][$group][$list])) {
            expect($child['settings'][$group][$list] ?? null)->toBeNull();

            continue;
        }

        $lists++;
        expect($child['settings'][$group][$list])->toBe($parent['settings'][$group][$list]);
    }

    // The repository's theme declares at least one list, or the comparison above proved nothing.
    expect($lists)->toBeGreaterThan(0);
});

it('FR-011: carries the whole of settings.custom and settings.layout', function () {
    $parent = parentThemeJson();
    $child  = json_decode((new ParentThemeTokens([parentThemeJsonPath()]))->childThemeJson(), true);

    expect($parent['settings'])->toHaveKeys(['custom', 'layout'])
        ->and($child['settings']['custom'])->toBe($parent['settings']['custom'])
        ->and($child['settings']['layout'])->toBe($parent['settings']['layout']);
});

it('FR-011: declares no styles, templateParts or customTemplates, and none of the parent\'s switches', function () {
    $parent  = parentThemeJson();
    $child   = json_decode((new ParentThemeTokens([parentThemeJsonPath()]))->childThemeJson(), true);
    $allowed = ['custom', 'layout'];

    foreach (ParentThemeTokens::PRESET_LISTS as [$group]) {
        $allowed[] = $group;
    }

    expect(array_keys($child))->toBe(['$schema', 'version', 'settings'])
        ->and($child['$schema'])->toBe($parent['$schema'])
        ->and($child['version'])->toBe($parent['version'])
        ->and(array_diff(array_keys($child['settings']), $allowed))->toBe([]);

    // Inside a preset group, only the lists: `fluid`, `defaultFontSizes`, `units` and the like stay the parent's.
    foreach (ParentThemeTokens::PRESET_LISTS as [$group]) {
        $lists = array_column(
            array_filter(ParentThemeTokens::PRESET_LISTS, static fn (array $path): bool => $path[0] === $group),
            1,
        );

        expect(array_diff(array_keys($child['settings'][$group] ?? []), $lists))->toBe([]);
    }
});

it('FR-011: invents no list the parent does not declare, and keeps one it gains', function () {
    $parent = parentThemeJson();
    // The palette gone, and a gradient list in its place, as a later release of the theme might have.
    $gained = $parent['settings']['color']['palette'];
    $parent['settings']['color'] = ['gradients' => $gained];

    $child = childBuiltFrom($parent);

    expect($child['settings']['color'])->toBe(['gradients' => $gained])
        ->and($child['settings']['color'])->not->toHaveKey('palette');
});

it('FR-011: keeps an empty object an object', function () {
    $file = parentThemeScratch() . '/theme.json';
    file_put_contents($file, '{"version":3,"settings":{"custom":{"empty":{}},"layout":{}}}');

    $written = (new ParentThemeTokens([$file]))->childThemeJson();

    expect($written)->toContain('"empty": {}')
        ->and($written)->toContain('"layout": {}');
});

it('FR-011: writes pretty JSON with tabs and unescaped slashes, ending in a newline', function () {
    $written = (new ParentThemeTokens([parentThemeJsonPath()]))->childThemeJson();

    expect($written)->toEndWith("}\n")
        ->and($written)->toContain("\n\t\"settings\": {")
        ->and($written)->not->toMatch('/^ +/m')
        ->and($written)->not->toContain('\\/');
});

it('FR-012: reads the first candidate that can be read and decoded', function () {
    $scratch = parentThemeScratch();
    file_put_contents($scratch . '/broken.json', '{ not json');

    $tokens = new ParentThemeTokens([
        $scratch . '/missing.json',
        $scratch . '/broken.json',
        parentThemeJsonPath(),
    ]);

    expect(json_decode($tokens->childThemeJson(), true)['settings']['custom'])
        ->toBe(parentThemeJson()['settings']['custom']);
});

it('FR-012: names every path it looked in when none can be read', function () {
    $scratch = parentThemeScratch();
    file_put_contents($scratch . '/broken.json', '{ not json');
    $paths = [$scratch . '/missing.json', $scratch . '/broken.json'];

    try {
        (new ParentThemeTokens($paths))->childThemeJson();
        $thrown = null;
    } catch (ParentThemeUnavailable $e) {
        $thrown = $e;
    }

    expect($thrown)->toBeInstanceOf(ParentThemeUnavailable::class)
        ->and($thrown?->paths)->toBe($paths)
        ->and($thrown?->getMessage())->toContain($paths[0])
        ->and($thrown?->getMessage())->toContain($paths[1]);
});

it('FR-012: does not take a JSON file that is not an object for a theme.json', function () {
    $file = parentThemeScratch() . '/theme.json';
    file_put_contents($file, '[1, 2]');

    expect(fn () => (new ParentThemeTokens([$file]))->childThemeJson())
        ->toThrow(ParentThemeUnavailable::class);
});
