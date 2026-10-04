<?php

/**
 * Unit tests for deciding whether a site being generated sits inside the repository's `sites/`
 * directory (spec 102, FR-005). Only then can make:site place the client's CI workflow, because a
 * workflow runs from `.github/workflows/` at the repository root and nowhere else.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Site\FrameworkBaseline;
use Corex\Cli\Site\FrameworkBaselineSource;
use Corex\Cli\Site\SiteRepositoryResolver;

function resolverFor(string $root): SiteRepositoryResolver
{
    $source = new class implements FrameworkBaselineSource {
        public function current(): FrameworkBaseline
        {
            return new FrameworkBaseline('v1.2.3', str_repeat('a', 40));
        }
    };

    return new SiteRepositoryResolver($source, $root);
}

it('knows the repository root for a site at <root>/sites/<client>', function () {
    $repository = resolverFor('/work/project')->for('/work/project/sites/acme');

    expect($repository->root)->toBe('/work/project')
        ->and($repository->baseline->release)->toBe('v1.2.3');
});

it('accepts either kind of path separator, and a trailing one', function () {
    $repository = resolverFor('C:\\work\\project')->for('C:\\work\\project\\sites\\acme\\');

    expect($repository->root)->toBe('C:/work/project');
});

it('reads a relative site directory against the directory the command ran in', function (string $given) {
    // `wp corex make:site Acme --path=sites/acme` is how the documentation writes it.
    $repository = resolverFor('/work/project')->for($given, '/work/project');

    expect($repository->root)->toBe('/work/project');
})->with(['sites/acme', './sites/acme', 'sites\\acme']);

it('does not mistake a relative path for one inside the repository when run from elsewhere', function () {
    $repository = resolverFor('/work/project')->for('sites/acme', '/work/project/docs');

    expect($repository->root)->toBeNull();
});

it('has no repository root for a site generated anywhere else', function (string $output) {
    $repository = resolverFor('/work/project')->for($output);

    expect($repository->root)->toBeNull()
        // The baseline is still recorded: where the site sits changes only whether a workflow
        // can be placed, not which framework it was generated against.
        ->and($repository->baseline->commit)->toBe(str_repeat('a', 40));
})->with([
    'the current directory'      => ['/work/project/acme'],
    'another repository'         => ['/work/elsewhere/sites/acme'],
    'one level too deep'         => ['/work/project/sites/group/acme'],
    'the sites directory itself' => ['/work/project/sites'],
]);
