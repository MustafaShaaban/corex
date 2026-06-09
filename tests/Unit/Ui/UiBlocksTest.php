<?php

/**
 * Unit tests for the Corex UI dynamic blocks (spec 009 US2: FR-002, FR-004, FR-005).
 *
 * Server-rendered, accessible, escaped, bounded output — verified headlessly (the posts
 * provider is faked at the boundary; WP functions stubbed via Brain Monkey).
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\BreadcrumbsRenderer;
use Corex\Ui\Blocks\CopyrightRenderer;
use Corex\Ui\Blocks\PostsProvider;
use Corex\Ui\Blocks\PostsRenderer;

function uiStubs(): void
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
}

/**
 * @param list<array{title:string,url:string}> $items
 */
function fakePosts(array $items): PostsProvider
{
    return new class($items) implements PostsProvider {
        /** @param list<array{title:string,url:string}> $items */
        public function __construct(private array $items)
        {
        }

        public function recent(int $count): array
        {
            return array_slice($this->items, 0, $count);
        }
    };
}

it('renders a copyright line with the year and site name', function () {
    uiStubs();
    Functions\when('get_bloginfo')->justReturn('Acme');

    $html = (new CopyrightRenderer())->render([], '', (object) []);

    expect($html)->toContain((string) gmdate('Y'))
        ->toContain('Acme')
        ->toContain('class="corex-copyright"');
});

it('renders an accessible breadcrumb trail to the current page', function () {
    uiStubs();
    Functions\when('home_url')->justReturn('https://example.test/');
    Functions\when('get_the_title')->justReturn('About');

    $html = (new BreadcrumbsRenderer())->render([], '', (object) []);

    expect($html)->toContain('<nav')
        ->toContain('aria-label=')
        ->toContain('About')
        ->toContain('aria-current="page"');
});

it('renders posts as accessible linked cards, bounded by count', function () {
    uiStubs();
    $provider = fakePosts([
        ['title' => 'First', 'url' => 'https://example.test/first'],
        ['title' => 'Second', 'url' => 'https://example.test/second'],
    ]);

    $html = (new PostsRenderer($provider))->render(['count' => 5], '', (object) []);

    expect($html)->toContain('First')
        ->toContain('Second')
        ->toContain('<article')
        ->toContain('<h3');
});

it('renders an accessible empty state when there are no posts', function () {
    uiStubs();

    $html = (new PostsRenderer(fakePosts([])))->render([], '', (object) []);

    expect($html)->toContain('class="corex-posts__empty"');
});
