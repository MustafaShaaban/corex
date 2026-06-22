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

it('scaffolds the plugin, theme, and governance set with the client identity', function () {
    $base   = tempSiteBase();
    $result = siteScaffolder()->scaffold('Acme', $base);

    expect($result->status)->toBe(SiteScaffoldResult::CREATED)
        ->and(is_file($base . '/plugins/acme-site/acme-site.php'))->toBeTrue()
        ->and(is_file($base . '/plugins/acme-site/src/AcmeSiteServiceProvider.php'))->toBeTrue()
        ->and(is_file($base . '/themes/acme/style.css'))->toBeTrue()
        ->and(is_file($base . '/themes/acme/theme.json'))->toBeTrue()
        ->and(is_file($base . '/AGENTS.md'))->toBeTrue()
        ->and(is_file($base . '/.gitignore'))->toBeTrue();
});

it('generates valid PHP and a valid theme.json', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    foreach (['/plugins/acme-site/acme-site.php', '/plugins/acme-site/src/AcmeSiteServiceProvider.php'] as $php) {
        exec('php -l ' . escapeshellarg($base . $php) . ' 2>&1', $out, $exit);
        expect($exit)->toBe(0);
    }

    expect(json_decode((string) file_get_contents($base . '/themes/acme/theme.json'), true))->toBeArray();
});

it('writes governance stating the client-only edit boundary + one-feature-one-PR', function () {
    $base = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $base);

    $agents = (string) file_get_contents($base . '/AGENTS.md');

    expect($agents)->toContain('do not edit Corex framework folders')
        ->and($agents)->toContain('plugins/acme-site/')
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

    expect(is_file($pluginOnly . '/plugins/acme-site/acme-site.php'))->toBeTrue()
        ->and(is_dir($pluginOnly . '/themes/acme'))->toBeFalse();

    $themeOnly = tempSiteBase();
    siteScaffolder()->scaffold('Acme', $themeOnly, ['theme_only' => true]);

    expect(is_file($themeOnly . '/themes/acme/style.css'))->toBeTrue()
        ->and(is_dir($themeOnly . '/plugins/acme-site'))->toBeFalse();
});
