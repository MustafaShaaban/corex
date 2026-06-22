<?php

/**
 * Docs URL resolver contract (company-site readiness, Part 4). Admin "Documentation" links must
 * resolve to an absolute URL — never a relative path that the browser would resolve against the
 * active (client) WordPress domain. With a configured docs base the path is appended to it; with
 * none, it falls back to the framework's canonical docs source on GitHub. A `corex_docs_base_url`
 * filter overrides the configured value.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use Corex\Config\Docs\DocsUrl;
use Corex\Support\Config\ConfigInterface;

beforeEach(function () {
    // apply_filters passes the value through unless a test adds a filter.
    Functions\when('apply_filters')->alias(static fn (string $hook, mixed $value) => $value);
});

/**
 * A DocsUrl backed by a stub config returning a fixed `docs.base_url`.
 */
function docsUrl(string $base): DocsUrl
{
    $config = new class ($base) implements ConfigInterface {
        public function __construct(private string $base)
        {
        }

        public function get(string $key, mixed $default = null): mixed
        {
            return $key === 'docs.base_url' ? $this->base : $default;
        }

        public function has(string $key): bool
        {
            return $key === 'docs.base_url';
        }
    };

    return new DocsUrl($config);
}

it('appends the path to a configured docs base', function () {
    expect(docsUrl('http://docs.corex.local')->resolve('/guides/media/'))
        ->toBe('http://docs.corex.local/guides/media/');
});

it('normalises slashes between the base and the path', function () {
    expect(docsUrl('http://docs.corex.local/')->resolve('guides/media/'))
        ->toBe('http://docs.corex.local/guides/media/');
});

it('falls back to the GitHub docs source when no base is configured', function () {
    // Never a relative path (which would resolve against the client site) — always absolute.
    $url = docsUrl('')->resolve('/guides/media/');

    expect($url)
        ->toStartWith('https://github.com/')
        ->toEndWith('/docs-app/src/content/docs/guides/media.md');
});

it('never returns a path that resolves against the active site', function () {
    foreach (['', 'http://docs.corex.local'] as $base) {
        expect(docsUrl($base)->resolve('/guides/configuration/'))
            ->toStartWith('http');
    }
});

it('returns an empty string for an empty path (no link)', function () {
    expect(docsUrl('http://docs.corex.local')->resolve(''))->toBe('');
});

it('passes an already-absolute URL through unchanged', function () {
    expect(docsUrl('')->resolve('https://example.test/x/'))->toBe('https://example.test/x/');
});

it('lets the corex_docs_base_url filter override the configured base', function () {
    Functions\when('apply_filters')->alias(
        static fn (string $hook, mixed $value) => $hook === 'corex_docs_base_url' ? 'http://filtered.local' : $value,
    );

    expect(docsUrl('http://config.local')->resolve('/guides/media/'))
        ->toBe('http://filtered.local/guides/media/')
        ->and(docsUrl('')->hasConfiguredBase())->toBeTrue();
});
