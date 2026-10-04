<?php

/**
 * Unit tests for the client-site scaffolder (make:site, spec 049, US1/US2).
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldResult;

function siteStubsDir(): string
{
    return dirname(__DIR__, 3) . '/packages/cli/stubs';
}

function tempSiteBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_site_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

function siteScaffolder(): SiteScaffolder
{
    return new SiteScaffolder(new StubRenderer(), siteStubsDir());
}

it('scaffolds the plugin, theme, and governance set under the flat sites/<client> layout', function () {
    $base   = tempSiteBase();
    $result = siteScaffolder()->scaffold('Acme', $base);

    expect($result->status)->toBe(SiteScaffoldResult::CREATED)
        // spec 061: client plugin/theme sit directly under the site root (no plugins/ or themes/ nesting)
        ->and(is_file($base . '/acme-site/acme-site.php'))->toBeTrue()
        ->and(is_file($base . '/acme-site/src/AcmeSiteServiceProvider.php'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/style.css'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/theme.json'))->toBeTrue()
        ->and(is_dir($base . '/plugins'))->toBeFalse()
        ->and(is_dir($base . '/themes'))->toBeFalse()
        ->and(is_file($base . '/AGENTS.md'))->toBeTrue()
        ->and(is_file($base . '/.gitignore'))->toBeTrue();
});

it('scaffolds header/footer/front-page override points with ownership guidance', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $header = (string) file_get_contents($base . '/acme-theme/parts/header.html');
    $footer = (string) file_get_contents($base . '/acme-theme/parts/footer.html');
    $front  = (string) file_get_contents($base . '/acme-theme/templates/front-page.html');

    expect(is_file($base . '/acme-theme/parts/header.html'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/parts/footer.html'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/templates/front-page.html'))->toBeTrue()
        // the override points explain brand-via-tokens vs structure-in-client-theme
        ->and($header)->toContain('override')
        ->and($header)->toContain('theme.json')
        ->and($footer)->toContain('override')
        ->and($front)->toContain('front page');
});

it('scaffolds the client image-optimization pipeline in --starter mode', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base, ['starter' => true]);

    $pkg = (string) file_get_contents($base . '/acme-theme/package.json');

    expect(is_file($base . '/acme-theme/scripts/optimize-images.mjs'))->toBeTrue()
        ->and(is_file($base . '/acme-theme/assets/src/images/.gitkeep'))->toBeTrue()
        ->and($pkg)->toContain('"images"')
        ->and($pkg)->toContain('npm run images')
        ->and($pkg)->toContain('sharp');
});

it('generates valid PHP and a valid theme.json', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    foreach (['/acme-site/acme-site.php', '/acme-site/src/AcmeSiteServiceProvider.php'] as $php) {
        exec('php -l ' . escapeshellarg($base . $php) . ' 2>&1', $out, $exit);
        expect($exit)->toBe(0);
    }

    expect(json_decode((string) file_get_contents($base . '/acme-theme/theme.json'), true))->toBeArray();
});

it('writes governance stating the client-only edit boundary + one-feature-one-PR', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $agents = (string) file_get_contents($base . '/AGENTS.md');

    expect($agents)->toContain('do not edit Corex framework folders')
        ->and($agents)->toContain('acme-site/')
        ->and($agents)->toContain('One feature = one branch = one spec folder = one PR')
        ->and($agents)->toContain('AcmeSite\\')
        // spec 061: the generated stub carries the Role Gate + handoff guidance for Client Site Mode.
        ->and($agents)->toContain('CLIENT SITE MODE')
        ->and($agents)->toContain('do not edit')
        ->and($agents)->toContain('dist/')
        ->and($agents)->toContain('NEXT STEP');
});

it('ignores local AI/cache folders in the generated .gitignore', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $gitignore = (string) file_get_contents($base . '/.gitignore');

    expect($gitignore)->toContain('.claude/local/')
        ->and($gitignore)->toContain('.corex/cache/')
        ->and($gitignore)->toContain('.ai/');
});

it('is idempotent without --force and regenerates with it', function () {
    $base = tempSiteBase();

    expect(siteScaffolder()->scaffold('Acme', $base)->status)->toBe(SiteScaffoldResult::CREATED)
        ->and(siteScaffolder()->scaffold('Acme', $base)->status)->toBe(SiteScaffoldResult::SKIPPED)
        ->and(siteScaffolder()->scaffold('Acme', $base, ['force' => true])->status)->toBe(SiteScaffoldResult::CREATED);
});

it('honours --plugin-only and --theme-only', function () {
    $pluginOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $pluginOnly, ['plugin_only' => true]);

    expect(is_file($pluginOnly . '/acme-site/acme-site.php'))->toBeTrue()
        ->and(is_dir($pluginOnly . '/acme-theme'))->toBeFalse();

    $themeOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $themeOnly, ['theme_only' => true]);

    expect(is_file($themeOnly . '/acme-theme/style.css'))->toBeTrue()
        ->and(is_dir($themeOnly . '/acme-site'))->toBeFalse();
});

// spec 101 — every new client theme starts with a coming-soon page it can edit (T052, FR-020).

it('gives a generated client theme its own coming-soon template', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $template = (string) file_get_contents($base . '/acme-theme/templates/coming-soon.html');

    preg_match_all('/<!-- wp:([a-z0-9\/-]+)/', $template, $blocks);
    preg_match_all('/<!-- wp:[a-z0-9\/-]+ (?:\{.*?\} )?-->/s', $template, $opened);
    preg_match_all('/<!-- \/wp:[a-z0-9\/-]+ -->/', $template, $closed);

    // The file name is the contract: `coming-soon` is the slug CoreX's default is registered
    // under, and a theme file of that name is what replaces it.
    expect($template)->not->toBe('')
        ->and($blocks[1])->not->toBeEmpty()
        // Core blocks only, so the page does not depend on any plugin's blocks being active.
        ->and(array_filter($blocks[1], static fn (string $name): bool => str_contains($name, '/')))->toBe([])
        // No header or footer: every link in one is redirected straight back to this page.
        ->and($template)->not->toContain('wp:template-part')
        ->and(count($opened[0]))->toBe(count($closed[0]))
        ->and($template)->toContain('Coming soon')
        // Nothing left for the renderer to fill in.
        ->and($template)->not->toMatch('/\{\{\s*[\w.]+\s*\}\}/');
});

it('tells whoever opens the generated template what it is and where its assets go', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $template = (string) file_get_contents($base . '/acme-theme/templates/coming-soon.html');

    expect($template)->toStartWith('<!--')
        ->and($template)->toContain('Acme')
        ->and($template)->toContain('Coming soon mode')
        // The action a designed page loads its own stylesheet and fonts on (T054).
        ->and($template)->toContain('corex_coming_soon_enqueue_assets');
});

it('generates the coming-soon template with the theme, and not without it', function () {
    $pluginOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $pluginOnly, ['plugin_only' => true]);

    $themeOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $themeOnly, ['theme_only' => true]);

    $starter = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $starter, ['starter' => true]);

    expect(is_file($pluginOnly . '/acme-theme/templates/coming-soon.html'))->toBeFalse()
        ->and(is_file($themeOnly . '/acme-theme/templates/coming-soon.html'))->toBeTrue()
        ->and(is_file($starter . '/acme-theme/templates/coming-soon.html'))->toBeTrue();
});
