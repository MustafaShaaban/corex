<?php

/**
 * Unit tests for the site plugin `make:site` generates (spec 135, slice 5, G-007): it boots its
 * provider through `Corex::onReady`, registers the blocks under its own `src/Blocks`, and builds them.
 *
 * The behaviour tests load the generated plugin in a PHP process of its own
 * (tests/Fixtures/Cli/boot-site-plugin.php), with CoreX faked or absent, as WordPress would load it.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Site\ParentThemeTokens;
use Corex\Cli\Site\SiteScaffolder;

function sitePluginRepository(): string
{
    return dirname(__DIR__, 3);
}

/**
 * A site generated into a folder of its own.
 *
 * @param array<string, bool> $options
 */
function generatedSite(array $options = []): string
{
    $base = sys_get_temp_dir() . '/corex_site_plugin_' . uniqid('', true);
    mkdir($base);

    (new SiteScaffolder(
        new StubRenderer(),
        sitePluginRepository() . '/packages/cli/stubs',
        new ParentThemeTokens([sitePluginRepository() . '/theme/theme.json'])
    ))->scaffold('Acme', $base, $options);

    return str_replace('\\', '/', $base);
}

/**
 * @return array{boots: int, beforeCorex: int, blockFolder: list<array{0: string, 1: string}>, singletons: list<string>, made: list<string>}
 */
function bootSitePlugin(string $pluginFile, string $scenario): array
{
    $runner = sitePluginRepository() . '/tests/Fixtures/Cli/boot-site-plugin.php';
    $output = [];

    exec(
        implode(' ', array_map('escapeshellarg', [PHP_BINARY, '-d', 'display_errors=1', '-d', 'error_reporting=-1', $runner, $pluginFile, $scenario]))
        . ' 2>&1',
        $output,
        $exit
    );

    $said   = implode("\n", $output);
    $result = json_decode($said, true);

    // One line of JSON and nothing else: a notice or a warning would be printed before it.
    expect($exit)->toBe(0, $said)
        ->and($output)->toHaveCount(1, $said)
        ->and($result)->toBeArray($said);

    return $result;
}

/** The framework's `@wordpress/scripts` range, read from the root package.json (FR-040). */
function frameworkScriptsRange(): string
{
    $root = json_decode((string) file_get_contents(sitePluginRepository() . '/package.json'), true);

    return $root['devDependencies']['@wordpress/scripts'];
}

// FR-030: the plugin file.

it('FR-030: the plugin file requires corex-core', function () {
    expect((string) file_get_contents(generatedSite() . '/acme-site/acme-site.php'))
        ->toMatch('/^ \* Requires Plugins:\s+corex-core$/m');
});

it('FR-030: boots the provider once, and never before CoreX, whichever loads first', function (string $scenario) {
    $result = bootSitePlugin(generatedSite() . '/acme-site/acme-site.php', $scenario);

    expect($result['beforeCorex'])->toBe(0)
        ->and($result['boots'])->toBe(1);
})->with([
    'CoreX has booted first'                             => 'booted',
    'CoreX boots after the site plugin'                  => 'later',
    'the plugin file is included before any CoreX class' => 'included-first',
    'the plugin is loaded after plugins_loaded'          => 'loaded-late',
]);

it('FR-030: does nothing and raises nothing when corex-core is not active', function () {
    $result = bootSitePlugin(generatedSite() . '/acme-site/acme-site.php', 'absent');

    expect($result['boots'])->toBe(0)
        ->and($result['blockFolder'])->toBe([]);
});

it('FR-030: boots each of two site plugins once, each its own provider', function () {
    $acme  = generatedSite();
    $other = sys_get_temp_dir() . '/corex_site_plugin_' . uniqid('', true);
    mkdir($other);
    (new SiteScaffolder(
        new StubRenderer(),
        sitePluginRepository() . '/packages/cli/stubs',
        new ParentThemeTokens([sitePluginRepository() . '/theme/theme.json'])
    ))->scaffold('Globex', $other, ['plugin_only' => true]);

    $both = sys_get_temp_dir() . '/corex_two_sites_' . uniqid('', true) . '.php';
    file_put_contents($both, sprintf(
        "<?php\nrequire %s;\nrequire %s;\n",
        var_export($acme . '/acme-site/acme-site.php', true),
        var_export(str_replace('\\', '/', $other) . '/globex-site/globex-site.php', true)
    ));

    $result = bootSitePlugin($both, 'later');

    expect($result['boots'])->toBe(2)
        ->and($result['blockFolder'])->toBe([
            [$acme . '/acme-site/build/blocks', $acme . '/acme-site/src/Blocks'],
            [str_replace('\\', '/', $other) . '/globex-site/build/blocks', str_replace('\\', '/', $other) . '/globex-site/src/Blocks'],
        ]);
});

// FR-031 and FR-032: the provider.

it('FR-031: the provider is the site\'s own class, not one of CoreX\'s', function () {
    expect((string) file_get_contents(generatedSite() . '/acme-site/src/AcmeSiteServiceProvider.php'))
        ->toContain('final class AcmeSiteServiceProvider' . "\n")
        ->not->toContain('extends');
});

it('FR-032: the provider asks the container for the block-folder service and hands it build/blocks and src/Blocks', function () {
    $site   = generatedSite();
    $result = bootSitePlugin($site . '/acme-site/acme-site.php', 'booted');

    expect($result['made'])->toBe([Corex\Blocks\BlockFolder::class])
        ->and($result['blockFolder'])->toBe([[$site . '/acme-site/build/blocks', $site . '/acme-site/src/Blocks']]);
});

it('FR-032: the provider registers no blocks when the container does not offer the service', function () {
    $result = bootSitePlugin(generatedSite() . '/acme-site/acme-site.php', 'no-block-folder');

    expect($result['boots'])->toBe(1)
        ->and($result['made'])->toBe([])
        ->and($result['blockFolder'])->toBe([]);
});

// FR-034: the build.

it('FR-034: the plugin\'s package.json builds src/Blocks to build/blocks', function () {
    $package = json_decode((string) file_get_contents(generatedSite() . '/acme-site/package.json'), true);

    expect($package)->toBeArray()
        ->and($package['name'])->toBe('acme-site')
        ->and($package['private'])->toBeTrue()
        ->and($package['scripts']['build'])->toBe('wp-scripts build --webpack-src-dir=src/Blocks --output-path=build/blocks')
        ->and($package['scripts']['start'])->toBe('wp-scripts start --webpack-src-dir=src/Blocks --output-path=build/blocks');
});

it('FR-034, FR-040: builds with the framework\'s @wordpress/scripts range', function (string $file) {
    $package = json_decode((string) file_get_contents(generatedSite(['starter' => true]) . $file), true);

    expect($package['devDependencies']['@wordpress/scripts'])->toBe(frameworkScriptsRange());
})->with([
    'the site plugin'     => '/acme-site/package.json',
    'the --starter theme' => '/acme-theme/package.json',
]);

it('FR-034: the build convention is the one corex-ui builds with', function () {
    $corexUi = json_decode((string) file_get_contents(sitePluginRepository() . '/addons/corex-ui/package.json'), true);
    $package = json_decode((string) file_get_contents(generatedSite() . '/acme-site/package.json'), true);

    expect($package['scripts']['build'])->toBe($corexUi['scripts']['build'])
        ->and($package['scripts']['start'])->toBe($corexUi['scripts']['start']);
});

it('FR-034: writes no package.json for the plugin when only the theme is generated', function () {
    $base = sys_get_temp_dir() . '/corex_site_plugin_' . uniqid('', true);
    mkdir($base);
    (new SiteScaffolder(
        new StubRenderer(),
        sitePluginRepository() . '/packages/cli/stubs',
        new ParentThemeTokens([sitePluginRepository() . '/theme/theme.json'])
    ))->scaffold('Acme', $base, ['theme_only' => true]);

    expect(is_file($base . '/acme-site/package.json'))->toBeFalse();
});

it('FR-034: the generated .gitignore keeps node_modules and build out of git at any depth', function () {
    $site  = generatedSite();
    $paths = [
        'node_modules/x.js',
        'acme-site/node_modules/@wordpress/scripts/package.json',
        'acme-theme/node_modules/sass/package.json',
        'acme-site/build/blocks/banner/block.json',
        'acme-theme/assets/build/x.js',
    ];

    exec('git -C ' . escapeshellarg($site) . ' init --quiet 2>&1', $initOutput, $initExit);
    exec(
        'git -C ' . escapeshellarg($site) . ' check-ignore --no-index ' . implode(' ', array_map('escapeshellarg', $paths)) . ' 2>&1',
        $ignored,
        $exit
    );

    expect($initExit)->toBe(0, implode("\n", $initOutput))
        ->and($exit)->toBe(0, implode("\n", $ignored))
        ->and($ignored)->toBe($paths);

    // What the site keeps: its block sources and its plugin's package.json.
    exec(
        'git -C ' . escapeshellarg($site) . ' check-ignore --no-index acme-site/src/Blocks/banner/block.json acme-site/package.json 2>&1',
        $kept,
        $keptExit
    );

    expect($keptExit)->toBe(1, implode("\n", $kept))
        ->and($kept)->toBe([]);
});

// FR-035: --starter takes the same route.

it('FR-035: --starter writes the same plugin file as a site without it', function () {
    $lean    = generatedSite();
    $starter = generatedSite(['starter' => true]);

    expect((string) file_get_contents($starter . '/acme-site/acme-site.php'))
        ->toBe((string) file_get_contents($lean . '/acme-site/acme-site.php'));
});

it('FR-035: the --starter provider binds its services on the container and builds nothing with new', function () {
    $site     = generatedSite(['starter' => true]);
    $provider = (string) file_get_contents($site . '/acme-site/src/AcmeSiteServiceProvider.php');
    $result   = bootSitePlugin($site . '/acme-site/acme-site.php', 'booted');

    expect($provider)->not->toMatch('/\bnew\s+[A-Z\\\\]/')
        ->and($result['boots'])->toBe(1)
        ->and($result['singletons'])->toBe(['AcmeSite\\Repositories\\ExampleRepository', 'AcmeSite\\Services\\ExampleService'])
        ->and($result['made'])->toBe([
            'AcmeSite\\Controllers\\ExampleController',
            'AcmeSite\\Options\\ExampleOptions',
            Corex\Blocks\BlockFolder::class,
        ])
        ->and($result['blockFolder'])->toBe([[$site . '/acme-site/build/blocks', $site . '/acme-site/src/Blocks']]);
});

it('FR-035: the example block is discovered in the plugin\'s block namespace, with a container-resolved renderer', function () {
    $site  = generatedSite(['starter' => true]);
    $block = json_decode((string) file_get_contents($site . '/acme-site/src/Blocks/example/block.json'), true);

    expect($block)->toBeArray()
        ->and($block['name'])->toBe('acme-site/example')
        ->and($block['textdomain'])->toBe('acme-site')
        ->and($block['corex']['renderer'])->toBe('AcmeSite\\Blocks\\ExampleRenderer')
        ->and($block['editorScript'])->toBe('file:./index.js')
        ->and($block['style'])->toBe('file:./style-index.css');
});

// FR-037: the generated README and AGENTS.md say how the site gets a block.

it('FR-037: the generated README and AGENTS.md say where a block goes and when it registers', function () {
    $site = generatedSite();

    expect((string) file_get_contents($site . '/README.md'))
        ->toContain('`wp corex make:block <Name>` writes one into `acme-site/src/Blocks/`')
        ->toContain('in `acme-site/`')
        ->and((string) file_get_contents($site . '/AGENTS.md'))
        ->toContain('A block from `make:block` is registered from')
        ->toContain('`acme-site/build/blocks` once `npm run build` has run in `acme-site/`');
});

// The owner's writing rule, not a requirement of the spec: no em dash in what the slice writes.
it('writes no em dash into the plugin, its provider or its package.json', function () {
    foreach ([generatedSite(), generatedSite(['starter' => true])] as $site) {
        foreach (['/acme-site/acme-site.php', '/acme-site/src/AcmeSiteServiceProvider.php', '/acme-site/package.json'] as $file) {
            expect((string) file_get_contents($site . $file))->not->toContain("\u{2014}");
        }
    }
});
