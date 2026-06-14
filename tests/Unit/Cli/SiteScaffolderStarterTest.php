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

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldResult;
use Corex\Cli\Support\InvalidNameException;

function starterScaffolder(): SiteScaffolder
{
    return new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs');
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

    $plugin = $base . '/plugins/acme-site';

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

    $theme = $base . '/themes/acme';

    foreach ([
        '/package.json',
        '/assets/src/main.scss',
        '/assets/src/main.js',
        '/inc/Assets.php',
        '/parts/footer.html',
    ] as $file) {
        expect(is_file($theme . $file))->toBeTrue("missing {$file}");
    }

    expect(json_decode((string) file_get_contents($theme . '/package.json'), true))->toBeArray();
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

    $controller = (string) file_get_contents($base . '/plugins/acme-site/src/Controllers/ExampleController.php');

    expect($controller)->toContain('Corex\\Http\\ResponseEnvelope')
        ->and($controller)->toContain('AcmeSite\\Controllers');
});

it('omits the slice by default and with --minimal', function () {
    foreach ([[], ['minimal' => true]] as $options) {
        $base = starterBase();
        starterScaffolder()->scaffold('Acme', $base, $options);

        expect(is_file($base . '/plugins/acme-site/src/Controllers/ExampleController.php'))->toBeFalse()
            ->and(is_file($base . '/plugins/acme-site/REMOVE-EXAMPLE.md'))->toBeFalse()
            ->and(is_dir($base . '/themes/acme/assets'))->toBeFalse();
    }
});

it('names the slice files in the removal guide', function () {
    $base = starterBase();
    starterScaffolder()->scaffold('Acme', $base, ['starter' => true]);

    $guide = (string) file_get_contents($base . '/plugins/acme-site/REMOVE-EXAMPLE.md');

    expect($guide)->toContain('src/Models/Example.php')
        ->and($guide)->toContain('src/Controllers/ExampleController.php')
        ->and($guide)->toContain('src/Blocks/example');
});

it('refuses a name that normalises to the reserved corex identity', function () {
    starterScaffolder()->scaffold('Corex', starterBase(), ['starter' => true]);
})->throws(InvalidNameException::class);
