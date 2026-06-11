<?php

/**
 * Unit tests for the Portfolio kit: the projects-grid renderer (bounded, escaped,
 * empty-state, thumbnail) and the blueprint manifest accuracy (declares only real
 * templates/parts/patterns).
 *
 * @package Corex\Tests\Unit\Portfolio
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Portfolio\Blocks\ProjectsProvider;
use Corex\Portfolio\Blocks\ProjectsRenderer;
use Corex\Portfolio\PortfolioBlueprint;
use Corex\Ui\Patterns\PatternLibrary;

/**
 * @param list<array{title:string,url:string,thumbnail:string}> $rows
 */
function fakeProjects(array $rows): ProjectsProvider
{
    return new class($rows) implements ProjectsProvider {
        /** @param list<array{title:string,url:string,thumbnail:string}> $rows */
        public function __construct(private array $rows)
        {
        }

        public function recent(int $count): array
        {
            return array_slice($this->rows, 0, $count);
        }
    };
}

function renderProjects(array $rows, array $attributes = []): string
{
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();

    return (new ProjectsRenderer(fakeProjects($rows)))->render($attributes, '', (object) []);
}

it('renders projects as accessible linked cards with thumbnails', function () {
    $html = renderProjects([
        ['title' => 'Alpha', 'url' => 'https://x.test/alpha', 'thumbnail' => 'https://x.test/a.jpg'],
        ['title' => 'Beta', 'url' => 'https://x.test/beta', 'thumbnail' => ''],
    ]);

    expect($html)
        ->toContain('class="corex-projects"')
        ->toContain('<a href="https://x.test/alpha">Alpha</a>')
        ->toContain('<img class="corex-projects__thumb" src="https://x.test/a.jpg"')
        ->toContain('<a href="https://x.test/beta">Beta</a>');

    // Beta has no thumbnail → no <img> for it (only one image total).
    expect(substr_count($html, '<img'))->toBe(1);
});

it('bounds the project count to the max', function () {
    $rows = array_map(
        fn (int $i): array => ['title' => "P{$i}", 'url' => "https://x.test/{$i}", 'thumbnail' => ''],
        range(1, 50)
    );

    $html = renderProjects($rows, ['count' => 999]);

    // Capped at 24.
    expect(substr_count($html, 'corex-projects__item'))->toBe(24);
});

it('renders an accessible empty state when there are no projects', function () {
    expect(renderProjects([]))->toContain('corex-projects__empty');
});

it('declares only templates/parts that exist and patterns the UI library provides', function () {
    $themeDir = dirname(__DIR__, 3) . '/theme';
    $kit = new PortfolioBlueprint();

    foreach ($kit->templates() as $template) {
        expect(is_file("{$themeDir}/templates/{$template}.html"))->toBeTrue("template {$template}");
    }
    foreach ($kit->parts() as $part) {
        expect(is_file("{$themeDir}/parts/{$part}.html"))->toBeTrue("part {$part}");
    }

    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();

    $patterns = array_column((new PatternLibrary())->patterns(), 'name');
    foreach ($kit->patterns() as $pattern) {
        expect($patterns)->toContain($pattern);
    }
});
