<?php

/**
 * Unit tests for the corex/carousel renderer (spec 068, US9 / FR-154, FR-162) — the scroll-snap
 * slider primitive: a region with a swipeable/keyboard-scrollable viewport of labelled slides plus
 * real prev/next/dot buttons, configurable per-view (1–6), opt-in autoplay, escaped and token-only.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\CarouselRenderer;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_attr__')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    Functions\when('esc_html')->alias(fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES));
    Functions\when('esc_attr')->alias(fn ($s) => htmlspecialchars((string) $s, ENT_QUOTES));
    $this->renderer = new CarouselRenderer();
});

/** @param array<int,array<string,string>> $slides */
function corexCarousel(CarouselRenderer $r, array $slides, array $extra = []): string
{
    return $r->render(array_merge(['slides' => $slides], $extra), '', (object) []);
}

it('renders a carousel region with a scroll-snap viewport of labelled slides', function () {
    $html = corexCarousel($this->renderer, [
        ['content' => 'One'],
        ['content' => 'Two'],
        ['content' => 'Three'],
    ]);

    expect($html)->toContain('class="corex-carousel corex-carousel--per-1"')
        ->and($html)->toContain('role="region"')
        ->and($html)->toContain('aria-roledescription="carousel"')
        ->and($html)->toContain('data-corex-carousel')
        ->and($html)->toContain('class="corex-carousel__viewport"')
        ->and($html)->toContain('class="corex-carousel__track"')
        ->and(substr_count($html, 'corex-carousel__slide'))->toBe(3)
        ->and($html)->toContain('One')
        ->and($html)->toContain('Three');
});

it('gives every slide a position label and each a real dot button', function () {
    $html = corexCarousel($this->renderer, [['content' => 'a'], ['content' => 'b']]);

    expect($html)->toContain('aria-roledescription="slide"')
        ->and($html)->toContain('1 of 2')
        ->and($html)->toContain('2 of 2')
        ->and(substr_count($html, 'data-corex-carousel-goto'))->toBe(2)
        ->and($html)->toContain('data-corex-carousel-goto="0"')
        ->and($html)->toContain('aria-current="true"')
        ->and($html)->toContain('data-corex-carousel-prev')
        ->and($html)->toContain('data-corex-carousel-next');
});

it('clamps per-view to the 1–6 range', function () {
    $low  = corexCarousel($this->renderer, [['content' => 'a']], ['perView' => 0]);
    $high = corexCarousel($this->renderer, [['content' => 'a']], ['perView' => 99]);
    $mid  = corexCarousel($this->renderer, [['content' => 'a']], ['perView' => 4]);

    expect($low)->toContain('corex-carousel--per-1')
        ->and($high)->toContain('corex-carousel--per-6')
        ->and($mid)->toContain('corex-carousel--per-4');
});

it('only marks the carousel for autoplay when explicitly enabled', function () {
    $off = corexCarousel($this->renderer, [['content' => 'a']]);
    $on  = corexCarousel($this->renderer, [['content' => 'a']], ['autoplay' => true]);

    expect($off)->not->toContain('data-corex-carousel-autoplay')
        ->and($on)->toContain('data-corex-carousel-autoplay');
});

it('skips empty slides and renders nothing when there are none', function () {
    $mixed = corexCarousel($this->renderer, [['content' => 'a'], ['content' => '  '], ['content' => 'b']]);

    expect(substr_count($mixed, 'corex-carousel__slide'))->toBe(2)
        ->and(corexCarousel($this->renderer, []))->toBe('')
        ->and(corexCarousel($this->renderer, [['content' => '']]))->toBe('');
});

it('escapes the accessible label and is token-only (no hardcoded hex)', function () {
    $html = corexCarousel($this->renderer, [['content' => 'a']], ['label' => '<script>x</script>']);

    expect($html)->not->toContain('<script>x</script>')
        ->and($html)->not->toMatch('/#[0-9a-fA-F]{3,6}\b/');
});
