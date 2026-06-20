<?php

/**
 * Spec 059 / M4 — Company Site Kit v1 page coverage.
 *
 * Keeps CompanyBlueprint::pages() honest: it must provide the full v1 content-page set,
 * exactly one front page, unique slugs, compose only patterns the UI library registers,
 * carry no raw color/size literals, and keep the same page set across demo levels.
 * (System surfaces — 404/search/single/archive — are owned by the universal templates.)
 *
 * @package Corex\Tests\Unit\Kit
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Kit\Company\CompanyBlueprint;
use Corex\Ui\Patterns\PatternLibrary;

function stubKitI18n(): void
{
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();
}

/** @return list<string> */
function expectedV1Slugs(): array
{
    return [
        'home', 'about', 'services', 'single-service', 'work', 'case-study', 'industries',
        'faq', 'blog', 'team', 'testimonials', 'locations', 'contact',
        'privacy-policy', 'terms', 'cookie-policy', 'maintenance',
    ];
}

it('provides the full v1 content-page set', function () {
    stubKitI18n();
    $slugs = array_column((new CompanyBlueprint())->pages(), 'slug');

    foreach (expectedV1Slugs() as $slug) {
        expect($slugs)->toContain($slug);
    }
});

it('marks exactly one front page and uses unique slugs', function () {
    stubKitI18n();
    $pages = (new CompanyBlueprint())->pages();

    $fronts = array_filter($pages, static fn (array $p): bool => ($p['front'] ?? false) === true);
    $slugs = array_column($pages, 'slug');

    expect($fronts)->toHaveCount(1)
        ->and($slugs)->toHaveCount(count(array_unique($slugs)));
});

it('composes only patterns the UI library registers', function () {
    stubKitI18n();
    $available = array_column((new PatternLibrary())->patterns(), 'name');

    foreach ((new CompanyBlueprint())->pages() as $page) {
        preg_match_all('/wp:pattern \{"slug":"(corex\/[a-z-]+)"\}/', $page['content'], $m);
        foreach ($m[1] as $pattern) {
            expect($available)->toContain($pattern);
        }
    }
});

it('uses no hardcoded colors or pixel sizes in any page content', function () {
    stubKitI18n();

    foreach ((new CompanyBlueprint())->pages() as $page) {
        expect($page['content'])->not->toMatch('/#[0-9a-fA-F]{3,6}\b/', "hex in {$page['slug']}")
            ->and($page['content'])->not->toMatch('/:\s*\d+px\b/', "px in {$page['slug']}");
    }
});

it('keeps the same page set across demo levels', function () {
    stubKitI18n();
    $blueprint = new CompanyBlueprint();

    $minimal = array_column($blueprint->pages('minimal'), 'slug');
    $standard = array_column($blueprint->pages('standard'), 'slug');
    $full = array_column($blueprint->pages('full'), 'slug');

    expect($standard)->toBe($minimal)
        ->and($full)->toBe($minimal);
});
