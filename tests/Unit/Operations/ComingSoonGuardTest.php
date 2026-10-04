<?php

/**
 * Unit tests for the one rule the Coming soon guard owns itself (spec 101, T022): which address is
 * the home URL. Everything else it does is either a fact WordPress supplies — proved against a real
 * install in ComingSoonModeTest — or a row of ComingSoonDecision.
 *
 * @package Corex\Tests\Unit\Operations
 */

declare(strict_types=1);

use Corex\Admin\StandalonePage;
use Corex\Config\Operations\ComingSoonGuard;
use Corex\Config\Operations\ComingSoonSitemap;
use Corex\Config\Operations\ComingSoonTemplate;
use Corex\Config\Operations\OperationsMode;
use Corex\Config\Operations\OperationsModeStore;

function comingSoonGuard(): ComingSoonGuard
{
    return new ComingSoonGuard(
        new OperationsModeStore(new OperationsMode()),
        new ComingSoonTemplate(new StandalonePage('', '')),
        new ComingSoonSitemap(),
    );
}

it('recognises the home URL of a site at the root of its domain', function (string $requestUri) {
    expect(comingSoonGuard()->isHome($requestUri, 'https://example.test/', []))->toBeTrue();
})->with([
    'the bare path'        => '/',
    'with a campaign tag'  => '/?utm_source=launch&utm_medium=email',
    'with an empty query'  => '/?',
    'with a fragment'      => '/#signup',
]);

it('recognises the home URL of a site installed in a directory, with or without its slash', function (string $requestUri) {
    expect(comingSoonGuard()->isHome($requestUri, 'https://example.test/acme/', []))->toBeTrue();
})->with(['/acme/', '/acme', '/acme/?utm_source=launch']);

it('does not mistake another address for the home URL', function (string $requestUri, string $homeUrl) {
    expect(comingSoonGuard()->isHome($requestUri, $homeUrl, []))->toBeFalse();
})->with([
    'a page'                              => ['/about/', 'https://example.test/'],
    'the front controller by name'        => ['/index.php', 'https://example.test/'],
    'the domain root of a directory site' => ['/', 'https://example.test/acme/'],
    'a page of a directory site'          => ['/acme/about/', 'https://example.test/acme/'],
    'a longer path that starts the same'  => ['/acme-old/', 'https://example.test/acme/'],
]);

it('does not call the home path home when WordPress matched a query variable in it', function (array $queryVars) {
    // The home path with `feed`, `p` or `s` on it is a feed, a post or a search. A feed is
    // rendered before WordPress chooses any template, so calling this address home would hand
    // the unfinished site's posts to anybody who asked for `/?feed=rss2`.
    expect(comingSoonGuard()->isHome('/?x=1', 'https://example.test/', $queryVars))->toBeFalse();
})->with([
    'a feed'   => [['feed' => 'rss2']],
    'a post'   => [['p' => '12']],
    'a search' => [['s' => 'launch']],
    'a page'   => [['page_id' => '2']],
]);
