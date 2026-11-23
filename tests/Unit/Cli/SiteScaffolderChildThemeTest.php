<?php

/**
 * Unit tests for the client theme make:site generates as a child of the CoreX theme (spec 135,
 * slice 3, G-006; DECISIONS #655 and #660).
 *
 * What the generated theme is compared with is read from the repository's `theme/theme.json`,
 * never written into a test (FR-040).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Site\ParentThemeTokens;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldResult;

function childThemeScaffolder(?ParentThemeTokens $parent = null): SiteScaffolder
{
    return new SiteScaffolder(
        new StubRenderer(),
        dirname(__DIR__, 3) . '/packages/cli/stubs',
        $parent ?? new ParentThemeTokens([dirname(__DIR__, 3) . '/theme/theme.json']),
    );
}

function childThemeBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_child_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

/** @return array<string,mixed> */
function childThemeParent(): array
{
    return json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/theme/theme.json'), true, 512, JSON_THROW_ON_ERROR);
}

/** @return list<string> Every file under a directory, relative to it. */
function childThemeFilesUnder(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
    }

    return $files;
}

it('FR-010: declares the CoreX theme as the parent and says so in the description', function () {
    $base = childThemeBase();
    childThemeScaffolder()->scaffold('Acme', $base);

    $style = (string) file_get_contents($base . '/acme-theme/style.css');

    expect($style)->toMatch('/^Template:\s*corex\s*$/m')
        ->and($style)->toMatch('/^Description:.*a child of the CoreX theme/m')
        ->and($style)->not->toContain('standalone')
        ->and($style)->not->toContain("\u{2014}");
});

it('FR-011: writes the CoreX theme\'s preset lists, custom tokens and layout into the child', function () {
    $base = childThemeBase();
    childThemeScaffolder()->scaffold('Acme', $base);

    $parent = childThemeParent();
    $child  = json_decode((string) file_get_contents($base . '/acme-theme/theme.json'), true, 512, JSON_THROW_ON_ERROR);

    foreach (ParentThemeTokens::PRESET_LISTS as [$group, $list]) {
        $theirs = $parent['settings'][$group][$list] ?? null;

        if ($theirs === null) {
            expect($child['settings'][$group][$list] ?? null)->toBeNull();

            continue;
        }

        // Same slugs in the same order, and the same values.
        expect(array_column($child['settings'][$group][$list], 'slug'))->toBe(array_column($theirs, 'slug'))
            ->and($child['settings'][$group][$list])->toBe($theirs);
    }

    expect($child['settings']['custom'])->toBe($parent['settings']['custom'])
        ->and($child['settings']['layout'])->toBe($parent['settings']['layout'])
        ->and($child)->not->toHaveKeys(['styles', 'templateParts', 'customTemplates']);
});

it('FR-011: keeps no second copy of the token lists in a stub', function () {
    expect(is_file(dirname(__DIR__, 3) . '/packages/cli/stubs/site/theme-json.stub'))->toBeFalse();
});

it('FR-013: writes the coming-soon template and leaves the index, front page, header and footer to the parent', function () {
    $base = childThemeBase();
    childThemeScaffolder()->scaffold('Acme', $base);

    expect(childThemeFilesUnder($base . '/acme-theme'))->toContain('templates/coming-soon.html')
        ->not->toContain('templates/index.html')
        ->not->toContain('templates/front-page.html')
        ->not->toContain('parts/header.html')
        ->not->toContain('parts/footer.html');
});

it('FR-012: writes nothing, and names every path it looked in, when the CoreX theme cannot be read', function () {
    $base    = childThemeBase();
    $missing = [$base . '/nowhere/theme/theme.json', $base . '/nowhere/themes/corex/theme.json'];
    $site    = $base . '/sites/acme';

    $result = childThemeScaffolder(new ParentThemeTokens($missing))->scaffold('Acme', $site);

    expect($result->status)->toBe(SiteScaffoldResult::ERROR)
        ->and($result->message)->toContain($missing[0])
        ->and($result->message)->toContain($missing[1])
        ->and($result->lookedIn)->toBe($missing)
        ->and(childThemeFilesUnder($site))->toBe([]);
});

it('FR-012: generates a --plugin-only site with no CoreX theme to read', function () {
    $base = childThemeBase();

    $result = childThemeScaffolder(new ParentThemeTokens([$base . '/nowhere.json']))
        ->scaffold('Acme', $base, ['plugin_only' => true]);

    expect($result->status)->toBe(SiteScaffoldResult::CREATED)
        ->and(is_file($base . '/acme-site/acme-site.php'))->toBeTrue()
        ->and(is_dir($base . '/acme-theme'))->toBeFalse();
});

it('FR-019: --force keeps an existing theme\'s style.css and theme.json byte for byte, and says so', function () {
    $base  = childThemeBase();
    $theme = $base . '/acme-theme';
    childThemeScaffolder()->scaffold('Acme', $base);

    // A theme generated before the child model: no Template line, and a token of its own.
    $style = "/*\nTheme Name: Acme\nDescription: The Acme theme.\n*/\n";
    $json  = "{\n\t\"version\": 3,\n\t\"settings\": { \"color\": { \"palette\": [ { \"slug\": \"own\", \"color\": \"#010203\", \"name\": \"Own\" } ] } }\n}\n";
    file_put_contents($theme . '/style.css', $style);
    file_put_contents($theme . '/theme.json', $json);
    file_put_contents($theme . '/templates/coming-soon.html', 'changed by hand');

    $result = childThemeScaffolder()->scaffold('Acme', $base, ['force' => true]);

    expect($result->status)->toBe(SiteScaffoldResult::CREATED)
        ->and(file_get_contents($theme . '/style.css'))->toBe($style)
        ->and(file_get_contents($theme . '/theme.json'))->toBe($json)
        // Every other generated file is rewritten as before.
        ->and(file_get_contents($theme . '/templates/coming-soon.html'))->not->toBe('changed by hand')
        ->and($result->kept)->toBe([$theme . '/style.css', $theme . '/theme.json'])
        ->and($result->paths)->not->toContain($theme . '/style.css')
        ->and($result->paths)->not->toContain($theme . '/theme.json');
});

it('FR-019: keeps an existing theme without reading the CoreX theme at all', function () {
    $base = childThemeBase();
    childThemeScaffolder()->scaffold('Acme', $base);

    $result = childThemeScaffolder(new ParentThemeTokens([$base . '/nowhere.json']))
        ->scaffold('Acme', $base, ['force' => true]);

    expect($result->status)->toBe(SiteScaffoldResult::CREATED)
        ->and($result->kept)->toHaveCount(2);
});

it('FR-019: keeps nothing on a first generation', function () {
    $base = childThemeBase();

    expect(childThemeScaffolder()->scaffold('Acme', $base)->kept)->toBe([]);
});

it('FR-010: says in the generated README that the theme is a child and what that means', function () {
    $base = childThemeBase();
    childThemeScaffolder()->scaffold('Acme', $base);

    $readme = (string) file_get_contents($base . '/README.md');

    expect($readme)->toContain('a child of the CoreX theme')
        ->and($readme)->toContain('## The theme')
        ->and($readme)->toContain('docs/en/04-team-workflow/client-site-workflow.md')
        ->and($readme)->toContain('npm run verify:client-theme')
        ->and($readme)->not->toContain("\u{2014}");
});

it('FR-010: describes no theme it generates as standalone', function () {
    $source = (string) file_get_contents(dirname(__DIR__, 3) . '/packages/cli/src/Site/SiteScaffolder.php');

    expect($source)->not->toMatch('/standalone/i')
        ->and($source)->toContain('child of the CoreX theme');
});
