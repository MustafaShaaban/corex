<?php

/**
 * Unit tests for the make:site --starter / --minimal vertical slice (spec 053, US4).
 * The starter emits a runnable, client-namespaced example (model→repository→service→
 * controller-on-envelope→block→option→test) + a starter-theme asset architecture + a
 * "how to remove" guide; the default and --minimal omit it.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\BlockPathResolver;
use Corex\Blocks\BlockWrapper;
use Corex\Blocks\DynamicBlockRegistrar;
use Corex\Blocks\PluginMountMap;
use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Site\ParentThemeTokens;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldResult;
use Corex\Cli\Support\InvalidNameException;
use Corex\Container\Container;
use Corex\Support\BootLogger;

function starterScaffolder(): SiteScaffolder
{
    return new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs', new ParentThemeTokens([dirname(__DIR__, 3) . '/theme/theme.json']));
}

function starterBase(): string
{
    $dir = sys_get_temp_dir() . '/corex_starter_' . uniqid('', true);
    mkdir($dir);

    return $dir;
}

it('emits the full example slice with --starter', function () {
    $base = starterBase();

    expect(starterScaffolder()->scaffold('Acme', $base, ['starter' => true])->status)
        ->toBe(SiteScaffoldResult::CREATED);

    $plugin = $base . '/acme-site';

    foreach ([
        '/src/Models/Example.php',
        '/src/Repositories/ExampleRepository.php',
        '/src/Services/ExampleService.php',
        '/src/Controllers/ExampleController.php',
        '/src/Blocks/ExampleRenderer.php',
        '/src/Blocks/example/block.json',
        '/src/Blocks/example/index.js',
        '/src/Blocks/example/style.scss',
        '/src/Options/ExampleOptions.php',
        '/tests/ExampleTest.php',
        '/REMOVE-EXAMPLE.md',
    ] as $file) {
        expect(is_file($plugin . $file))->toBeTrue("missing {$file}");
    }
});

it('emits the starter-theme asset architecture with --starter', function () {
    $base = starterBase();
    starterScaffolder()->scaffold('Acme', $base, ['starter' => true]);

    $theme = $base . '/acme-theme';

    foreach ([
        '/package.json',
        '/functions.php',
        '/assets/src/scss/main.scss',
        '/assets/src/js/main.js',
    ] as $file) {
        expect(is_file($theme . $file))->toBeTrue("missing {$file}");
    }

    $pkg = json_decode((string) file_get_contents($theme . '/package.json'), true);
    expect($pkg)->toBeArray()
        // spec 062: styles/scripts/images/build pipeline
        ->and($pkg['scripts'])->toHaveKeys(['styles', 'scripts', 'images', 'build'])
        ->and($pkg['devDependencies'])->toHaveKey('sass');

    // functions.php enqueues compiled assets through the CoreX helpers — not hardcoded paths.
    $functions = (string) file_get_contents($theme . '/functions.php');
    expect($functions)->toContain('Corex\\Assets\\Style::enqueue')
        ->toContain('Corex\\Assets\\Script::enqueue')
        ->toContain("'css/main.css'")
        ->not->toContain('get_stylesheet_directory_uri() . \'/assets/css');
});

it('generates only valid PHP across the starter slice', function () {
    $base = starterBase();
    starterScaffolder()->scaffold('Acme', $base, ['starter' => true]);

    $phpFiles = [];
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->getExtension() === 'php') {
            $phpFiles[] = $file->getPathname();
        }
    }

    expect($phpFiles)->not->toBeEmpty();

    foreach ($phpFiles as $php) {
        $out = [];
        exec('php -l ' . escapeshellarg($php) . ' 2>&1', $out, $exit);
        expect($exit)->toBe(0, $php . ' — ' . implode("\n", $out));
    }
});

it('uses the spec-043 response envelope in the generated controller', function () {
    $base = starterBase();
    starterScaffolder()->scaffold('Acme', $base, ['starter' => true]);

    $controller = (string) file_get_contents($base . '/acme-site/src/Controllers/ExampleController.php');

    expect($controller)->toContain('Corex\\Http\\ResponseEnvelope')
        ->and($controller)->toContain('AcmeSite\\Controllers');
});

it('omits the slice by default and with --minimal', function () {
    foreach ([[], ['minimal' => true]] as $options) {
        $base = starterBase();
        starterScaffolder()->scaffold('Acme', $base, $options);

        expect(is_file($base . '/acme-site/src/Controllers/ExampleController.php'))->toBeFalse()
            ->and(is_file($base . '/acme-site/REMOVE-EXAMPLE.md'))->toBeFalse()
            ->and(is_dir($base . '/acme-theme/assets'))->toBeFalse();
    }
});

it('names the slice files in the removal guide', function () {
    $base = starterBase();
    starterScaffolder()->scaffold('Acme', $base, ['starter' => true]);

    $guide = (string) file_get_contents($base . '/acme-site/REMOVE-EXAMPLE.md');

    expect($guide)->toContain('src/Models/Example.php')
        ->and($guide)->toContain('src/Controllers/ExampleController.php')
        ->and($guide)->toContain('src/Blocks/example');
});

it('spec 136 FR-014: the example block declares its kind, its anchor and a top and bottom margin, and previews its records inert', function () {
    $base = starterBase();
    starterScaffolder()->scaffold('Acme', $base, ['starter' => true]);
    $block = $base . '/acme-site/src/Blocks/example';

    $json = json_decode((string) file_get_contents($block . '/block.json'), true);
    $js = (string) file_get_contents($block . '/index.js');

    expect($json['corex']['editor'])->toBe('records')
        ->and($json['supports'])->toBe(['html' => false, 'anchor' => true, 'spacing' => ['margin' => ['top', 'bottom']]])
        ->and($js)->toContain("import ServerSideRender from '@wordpress/server-side-render';")
        ->toContain('<div { ...useBlockProps() }>')
        ->toContain('<Disabled>');
});

/**
 * Generates a starter under a name of its own (its classes load once per run), loads its classes and
 * returns the render callback CoreX gives its example block: the one DynamicBlockRegistrar builds from
 * the `corex.renderer` its block.json names, as BlockFolder registers it (spec 135, FR-032).
 */
function starterBlockCallback(BlockWrapper $wrapper): callable
{
    $base = starterBase();
    $name = 'Wrap' . substr(str_shuffle('abcdefghijklmnopqrstuvwxyz'), 0, 8);
    starterScaffolder()->scaffold($name, $base, ['starter' => true]);

    $provider = glob($base . '/*-site/src/*ServiceProvider.php')[0];
    $namespace = basename($provider, 'ServiceProvider.php');
    $src = dirname($provider);
    spl_autoload_register(static function (string $class) use ($namespace, $src): void {
        if (str_starts_with($class, $namespace . '\\')) {
            $file = $src . '/' . str_replace('\\', '/', substr($class, strlen($namespace) + 1)) . '.php';
            if (is_file($file)) {
                require $file;
            }
        }
    });

    Functions\when('get_option')->justReturn([['id' => 1, 'title' => 'First']]);
    Functions\stubEscapeFunctions();
    Functions\stubTranslationFunctions();

    $metadata = json_decode((string) file_get_contents($src . '/Blocks/example/block.json'), true);
    $registrar = new DynamicBlockRegistrar(
        new Container(),
        new BootLogger(debug: false),
        new PluginMountMap(),
        new BlockPathResolver(),
        $wrapper,
    );

    return $registrar->renderCallback($metadata['corex']['renderer']);
}

it('spec 136 FR-001, spec 135 FR-032: the example block\'s renderer is resolved from the container and its markup passes through the BlockWrapper', function () {
    $wrapper = new class implements BlockWrapper {
        /** @var list<string> */
        public array $seen = [];

        public function apply(string $markup, array $rootClasses = []): string
        {
            $this->seen[] = $markup;

            return str_replace('<ul class=', '<ul id="wrapped" class=', $markup);
        }
    };

    $wrapped = starterBlockCallback($wrapper)([], '', (object) []);

    expect($wrapper->seen)->toHaveCount(1)
        ->and($wrapper->seen[0])->toStartWith('<ul class=')
        ->and($wrapped)->toContain('<ul id="wrapped" class=')
        ->and($wrapped)->toContain('<li>First</li>');
});

it('refuses a name that normalises to the reserved corex identity', function () {
    starterScaffolder()->scaffold('Corex', starterBase(), ['starter' => true]);
})->throws(InvalidNameException::class);
