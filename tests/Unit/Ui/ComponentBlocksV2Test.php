<?php

/**
 * Unit tests for the v2 corex/* component block renderers (spec 035: hero, cta, team, gallery,
 * tabs). WordPress escaping is stubbed at the boundary (returnArg), as in ComponentBlocksTest.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Corex\Ui\Blocks\CtaRenderer;
use Corex\Ui\Blocks\GalleryRenderer;
use Corex\Ui\Blocks\HeroRenderer;
use Corex\Ui\Blocks\TabsRenderer;
use Corex\Ui\Blocks\TeamRenderer;
use Brain\Monkey\Functions;

beforeEach(function () {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('esc_url_raw')->returnArg();
    Functions\when('wp_unique_id')->alias(fn (string $prefix = '') => $prefix . '1');
});

$block = (object) [];

// ── Hero ───────────────────────────────────────────────────────────────────────
it('renders a hero with a heading + gated CTA + background image, empty title -> nothing', function () use ($block) {
    $html = (new HeroRenderer())->render([
        'eyebrow'  => 'New',
        'title'    => 'Build faster',
        'subtitle' => 'A WordPress framework.',
        'ctaText'  => 'Start',
        'ctaUrl'   => 'https://example.com',
        'image'    => ['url' => 'https://cdn/bg.jpg', 'alt' => 'Backdrop'],
    ], '', $block);

    expect($html)->toContain('<section class="corex-hero">')
        ->toContain('class="corex-hero__eyebrow">New')
        ->toContain('corex-hero__title">Build faster')
        ->toContain('corex-hero__subtitle">A WordPress framework.')
        ->toContain('<a class="corex-hero__cta" href="https://example.com">Start</a>')
        ->toContain('<img class="corex-hero__bg" src="https://cdn/bg.jpg" alt="Backdrop" loading="lazy"')
        ->and((new HeroRenderer())->render(['ctaText' => 'x'], '', $block))->toBe('');
});

it('opts hero/gallery/team images into the corex_media_optimize_image delivery seam (class preserved)', function () use ($block) {
    // Simulate Corex Media active: the filter wraps the <img> (and must receive the block's class/url).
    Functions\when('apply_filters')->alias(function (string $hook, $value, array $args = []) {
        if ($hook !== 'corex_media_optimize_image') {
            return $value;
        }

        return sprintf('<picture data-class="%s" data-url="%s">%s</picture>', $args['class'] ?? '', $args['url'] ?? '', $value);
    });

    $hero = (new HeroRenderer())->render(['title' => 'T', 'ctaText' => 'C', 'image' => ['url' => 'https://cdn/bg.jpg', 'alt' => 'a']], '', $block);
    $gallery = (new GalleryRenderer())->render(['images' => [['url' => 'https://cdn/1.jpg', 'alt' => 'o']]], '', $block);
    $team = (new TeamRenderer())->render(['members' => [['name' => 'A', 'image' => ['url' => 'https://cdn/a.jpg', 'alt' => 'a']]]], '', $block);

    expect($hero)->toContain('<picture data-class="corex-hero__bg" data-url="https://cdn/bg.jpg">')
        ->toContain('<img class="corex-hero__bg"');
    expect($gallery)->toContain('<picture data-class="corex-gallery__img" data-url="https://cdn/1.jpg">');
    expect($team)->toContain('<picture data-class="corex-team__photo" data-url="https://cdn/a.jpg">');
});

it('omits the hero CTA unless both text and url are set', function () use ($block) {
    expect((new HeroRenderer())->render(['title' => 'T', 'ctaText' => 'Go'], '', $block))
        ->not->toContain('corex-hero__cta');
});

// ── CTA ────────────────────────────────────────────────────────────────────────
it('renders a cta banner with a gated button, empty title -> nothing', function () use ($block) {
    $html = (new CtaRenderer())->render([
        'title'   => 'Ready?',
        'text'    => 'Ship today.',
        'ctaText' => 'Get started',
        'ctaUrl'  => 'https://example.com/go',
    ], '', $block);

    expect($html)->toContain('<div class="corex-cta">')
        ->toContain('corex-cta__title">Ready?')
        ->toContain('corex-cta__text">Ship today.')
        ->toContain('<a class="corex-cta__button" href="https://example.com/go">Get started</a>')
        ->and((new CtaRenderer())->render(['text' => 'x'], '', $block))->toBe('');
});

// ── Team ───────────────────────────────────────────────────────────────────────
it('renders a team grid from members[], skips nameless, empty -> nothing', function () use ($block) {
    $html = (new TeamRenderer())->render([
        'members' => [
            ['name' => 'Sam', 'role' => 'CTO', 'image' => ['url' => 'https://cdn/sam.jpg', 'alt' => 'Sam'], 'bio' => 'Builds things.'],
            ['name' => '', 'role' => 'Ghost'],
        ],
    ], '', $block);

    expect($html)->toContain('<div class="corex-team">')
        ->toContain('<figure class="corex-team__member">')
        ->toContain('<img class="corex-team__photo" src="https://cdn/sam.jpg" alt="Sam" loading="lazy"')
        ->toContain('corex-team__name">Sam')
        ->toContain('corex-team__role">CTO')
        ->toContain('corex-team__bio">Builds things.')
        ->not->toContain('Ghost');

    expect((new TeamRenderer())->render(['members' => [['name' => '']]], '', $block))->toBe('');
});

// ── Gallery ────────────────────────────────────────────────────────────────────
it('renders a gallery from images[], skips url-less, empty -> nothing', function () use ($block) {
    $html = (new GalleryRenderer())->render([
        'images' => [
            ['url' => 'https://cdn/1.jpg', 'alt' => 'One', 'caption' => 'First'],
            ['url' => '', 'alt' => 'missing'],
        ],
    ], '', $block);

    expect($html)->toContain('<div class="corex-gallery">')
        ->toContain('<figure class="corex-gallery__item">')
        ->toContain('<img class="corex-gallery__img" src="https://cdn/1.jpg" alt="One" loading="lazy"')
        ->toContain('<figcaption class="corex-gallery__caption">First')
        ->not->toContain('missing');

    expect((new GalleryRenderer())->render(['images' => [['url' => '']]], '', $block))->toBe('');
});

// ── Tabs ───────────────────────────────────────────────────────────────────────
it('renders CSS-only tabs from tabs[], first checked, skips label-less, empty -> nothing', function () use ($block) {
    $html = (new TabsRenderer())->render([
        'tabs' => [
            ['label' => 'Overview', 'content' => 'Intro text.'],
            ['label' => 'Details', 'content' => 'More text.'],
            ['label' => '', 'content' => 'orphan'],
        ],
    ], '', $block);

    expect($html)->toContain('<div class="corex-tabs">')
        ->toContain('type="radio"')
        ->toContain('name="corex-tabs-1"')
        ->toContain('checked')
        ->toContain('class="corex-tabs__label"')
        ->toContain('Overview')
        ->toContain('class="corex-tabs__panel"')
        ->toContain('Intro text.')
        ->not->toContain('orphan');

    expect((new TabsRenderer())->render(['tabs' => []], '', $block))->toBe('');
});

it('uses no hardcoded colors, pixel sizes, or inline styles in any v2 markup (token-only)', function () use ($block) {
    $markup = (new HeroRenderer())->render(['title' => 'T', 'subtitle' => 's', 'ctaText' => 'C', 'ctaUrl' => 'https://e.com', 'image' => ['url' => 'https://cdn/x.jpg', 'alt' => 'a']], '', $block)
        . (new CtaRenderer())->render(['title' => 'T', 'text' => 'x', 'ctaText' => 'C', 'ctaUrl' => 'https://e.com'], '', $block)
        . (new TeamRenderer())->render(['members' => [['name' => 'A', 'role' => 'r', 'image' => ['url' => 'https://cdn/a.jpg', 'alt' => 'a'], 'bio' => 'b']]], '', $block)
        . (new GalleryRenderer())->render(['images' => [['url' => 'https://cdn/1.jpg', 'alt' => 'o', 'caption' => 'c']]], '', $block)
        . (new TabsRenderer())->render(['tabs' => [['label' => 'L', 'content' => 'c']]], '', $block);

    expect($markup)->not->toMatch('/#[0-9a-fA-F]{3,6}\b/')
        ->not->toMatch('/\b\d+px\b/')
        ->not->toMatch('/style\s*=/');
});
