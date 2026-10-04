<?php

/**
 * Unit tests for what make:site generates so that a client site can take framework updates
 * (spec 102): the baseline record, the client's copy of the update checklist, the client's own CI
 * workflow, and the rule stated in the agent files.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Release\ReadinessFinding;
use Corex\Cli\Site\FrameworkBaseline;
use Corex\Cli\Site\SiteRepository;
use Corex\Cli\Site\SiteScaffolder;
use Corex\Cli\Site\SiteScaffoldValidator;

const UPDATE_SAFETY_COMMIT = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

function updateSafetyScaffolder(): SiteScaffolder
{
    return new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs');
}

/** A temporary repository root; the site is generated at `<root>/sites/acme`. */
function updateSafetyRepository(): string
{
    $root = sys_get_temp_dir() . '/corex_update_safety_' . uniqid('', true);
    mkdir($root . '/sites', 0755, true);

    return $root;
}

function updateSafetyBaseline(): FrameworkBaseline
{
    return new FrameworkBaseline('v1.2.3', UPDATE_SAFETY_COMMIT);
}

/** @return list<string> Every file under a directory, relative to it, with forward slashes. */
function updateSafetyFilesUnder(string $dir): array
{
    $files = [];

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS)) as $file) {
        $files[] = str_replace('\\', '/', substr($file->getPathname(), strlen($dir) + 1));
    }

    sort($files);

    return $files;
}

it('records the framework release and commit the site was generated against', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme', [], new SiteRepository(updateSafetyBaseline()));

    $record = json_decode((string) file_get_contents($root . '/sites/acme/corex-baseline.json'), true);

    expect($record['release'])->toBe('v1.2.3')
        ->and($record['commit'])->toBe(UPDATE_SAFETY_COMMIT)
        ->and($record['recorded'])->toMatch('/^\d{4}-\d{2}-\d{2}$/')
        ->and($record['exceptions'])->toBe([]);
});

it('still writes a record, with an empty commit, when the framework commit is not known', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme');

    $record = json_decode((string) file_get_contents($root . '/sites/acme/corex-baseline.json'), true);

    // An empty commit is what makes `verify:framework` fail with "record one" rather than
    // compare against a guess (FR-009).
    expect($record['commit'])->toBe('')
        ->and($record['exceptions'])->toBe([]);
});

it('writes the client its own copy of the update checklist', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme', [], new SiteRepository(updateSafetyBaseline()));

    $checklist = (string) file_get_contents($root . '/sites/acme/UPDATING-COREX.md');

    expect($checklist)->toContain('npm run verify:framework')
        ->and($checklist)->toContain('--record')
        ->and($checklist)->toContain('wp corex migrate')
        ->and($checklist)->toContain('sites/acme/acme-site')
        ->and($checklist)->toContain('sites/acme/acme-theme');
});

it('places the client workflow at the repository root, under a client-specific name', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold(
        'Acme',
        $root . '/sites/acme',
        ['starter' => true],
        new SiteRepository(updateSafetyBaseline(), $root),
    );

    $workflow = (string) file_get_contents($root . '/.github/workflows/site-acme.yml');

    expect($workflow)->toContain('node scripts/verify-framework.mjs')
        // The baseline commit is absent from a shallow clone.
        ->and($workflow)->toContain('fetch-depth: 0')
        ->and($workflow)->toContain('sites/acme/acme-site')
        ->and($workflow)->toContain('sites/acme/acme-theme');
});

it('generates a workflow with nothing left for the stub renderer or for GitHub to substitute', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme', [], new SiteRepository(updateSafetyBaseline(), $root));

    $workflow = (string) file_get_contents($root . '/.github/workflows/site-acme.yml');

    // StubRenderer throws on any `{{ word }}` it did not fill, and its pattern matches a GitHub
    // expression — so the workflow is written without one.
    expect($workflow)->not->toContain('{{')
        ->and($workflow)->not->toContain('}}');
});

it('names the workflow after the site directory, which need not be the site name', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme-eu', [], new SiteRepository(updateSafetyBaseline(), $root));

    expect(is_file($root . '/.github/workflows/site-acme-eu.yml'))->toBeTrue()
        ->and((string) file_get_contents($root . '/.github/workflows/site-acme-eu.yml'))
            ->toContain('sites/acme-eu/acme-site');
});

it('writes no workflow when it is not told where the repository is', function () {
    $root = updateSafetyRepository();
    $result = updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme', [], new SiteRepository(updateSafetyBaseline()));

    expect(is_dir($root . '/.github'))->toBeFalse()
        ->and(array_filter($result->paths, static fn (string $path): bool => str_contains($path, '.github')))->toBe([]);
});

it('generates none of it for --plugin-only or --theme-only', function (string $option) {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold(
        'Acme',
        $root . '/sites/acme',
        [$option => true],
        new SiteRepository(updateSafetyBaseline(), $root),
    );

    expect(is_file($root . '/sites/acme/corex-baseline.json'))->toBeFalse()
        ->and(is_file($root . '/sites/acme/UPDATING-COREX.md'))->toBeFalse()
        ->and(is_dir($root . '/.github'))->toBeFalse();
})->with(['plugin_only', 'theme_only']);

it('states the rule that keeps an update conflict-free in both agent files', function (string $file) {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme');

    $contents = (string) file_get_contents($root . '/sites/acme/' . $file);

    expect($contents)->toContain('framework-owned')
        ->and($contents)->toContain('npm run verify:framework')
        ->and($contents)->toContain('UPDATING-COREX.md');
})->with(['AGENTS.md', 'CLAUDE.md']);

it('no longer tells an agent the framework lives in another repository', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme');

    expect((string) file_get_contents($root . '/sites/acme/AGENTS.md'))
        ->not->toContain('The framework lives in its own')
        ->toContain('this repository');
});

it('gives the starter test a configuration that runs it', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme', ['starter' => true]);

    $plugin = $root . '/sites/acme/acme-site';
    exec('php -l ' . escapeshellarg($plugin . '/tests/bootstrap.php') . ' 2>&1', $output, $exit);

    expect($exit)->toBe(0)
        ->and(simplexml_load_string((string) file_get_contents($plugin . '/phpunit.xml.dist')))->not->toBeFalse()
        ->and((string) file_get_contents($plugin . '/tests/bootstrap.php'))->toContain('AcmeSite\\\\');
});

it('keeps the test run\'s cache out of the client\'s commits', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme', ['starter' => true]);

    // phpunit.xml.dist names this directory, so the first test run creates it in the plugin.
    expect((string) file_get_contents($root . '/sites/acme/.gitignore'))->toContain('.phpunit.cache/');
});

it('counts the baseline record and the checklist as required governance', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme');

    $finding = (new SiteScaffoldValidator())->validate($root . '/sites/acme', 'minimal');

    expect($finding->status)->toBe(ReadinessFinding::STATUS_PASS)
        ->and($finding->evidence)->toContain('corex-baseline.json', 'UPDATING-COREX.md');
});

it('fails readiness for a scaffold that has lost its baseline record or its checklist', function (string $file) {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme');
    unlink($root . '/sites/acme/' . $file);

    $finding = (new SiteScaffoldValidator())->validate($root . '/sites/acme', 'minimal');

    expect($finding->status)->toBe(ReadinessFinding::STATUS_FAIL)
        ->and($finding->evidence)->toContain('missing:' . $file);
})->with(['corex-baseline.json', 'UPDATING-COREX.md']);

it('adds the test configuration only with --starter', function () {
    $root = updateSafetyRepository();
    updateSafetyScaffolder()->scaffold('Acme', $root . '/sites/acme');

    expect(updateSafetyFilesUnder($root . '/sites/acme/acme-site'))->not->toContain('phpunit.xml.dist');
});
