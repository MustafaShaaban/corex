<?php

/**
 * Unit tests for where the `make:*` generators write in a client repository (#251, item 2).
 *
 * With no `app.path` configured the generators wrote to `wp-content/corex-app` under `App\`, while
 * the generated `AGENTS.md` said they wrote into the client plugin. Reported 2026-10-06 from the
 * first client repository created from v0.43.0.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Corex\Cli\Generators\StubRenderer;
use Corex\Cli\Site\ClientSitePlugin;
use Corex\Cli\Site\SiteScaffolder;

function repositoryWithSites(string ...$names): string
{
    $root = sys_get_temp_dir() . '/corex_repo_' . uniqid('', true);
    mkdir($root . '/sites', 0777, true);

    $scaffolder = new SiteScaffolder(new StubRenderer(), dirname(__DIR__, 3) . '/packages/cli/stubs');
    foreach ($names as $name) {
        $scaffolder->scaffold($name, $root . '/sites/' . strtolower($name));
    }

    return $root;
}

it('points the generators at the client plugin of a repository with one site', function () {
    $root    = repositoryWithSites('Acme');
    $context = (new ClientSitePlugin())->generatorContext($root);

    expect($context)->not->toBeNull()
        ->and(str_replace('\\', '/', $context->basePath))->toBe(str_replace('\\', '/', $root) . '/sites/acme/acme-site/src')
        ->and($context->namespace)->toBe('AcmeSite')
        ->and($context->prefix)->toBe('acme-site');
});

it('names no plugin when the repository cannot say which one is meant', function (array $sites) {
    expect((new ClientSitePlugin())->generatorContext(repositoryWithSites(...$sites)))->toBeNull();
})->with([
    'the framework’s own repository, with no site' => [[]],
    'a repository with two sites' => [['Acme', 'Beta']],
]);

it('names no plugin in a directory that has no sites at all', function () {
    $root = sys_get_temp_dir() . '/corex_repo_' . uniqid('', true);
    mkdir($root);

    expect((new ClientSitePlugin())->generatorContext($root))->toBeNull();
});
