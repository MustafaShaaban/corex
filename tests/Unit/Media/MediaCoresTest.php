<?php

/**
 * Unit tests for the media pure cores (spec 048): capability, conversion plan, picture markup.
 *
 * @package Corex\Tests\Unit\Media
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Media\ConversionPlan;
use Corex\Media\ImageCapability;
use Corex\Media\PictureRenderer;

it('reports webp capability only with a converter and webp support', function () {
    expect((new ImageCapability(true, false, true, false))->canWebp())->toBeTrue()
        ->and((new ImageCapability(true, false, false, false))->canWebp())->toBeFalse()
        ->and((new ImageCapability(false, false, true, false))->canWebp())->toBeFalse()
        ->and((new ImageCapability(false, false, false, false))->canConvert())->toBeFalse();
});

it('plans a webp conversion for a jpeg/png, preserving the original path', function () {
    $cap  = new ImageCapability(true, false, true, false);
    $plan = ConversionPlan::for('/uploads/photo.jpg', 'image/jpeg', $cap);

    expect($plan->convert)->toBeTrue()
        ->and($plan->format)->toBe('webp')
        ->and($plan->outputPath)->toBe('/uploads/photo.webp')
        ->and(ConversionPlan::for('/uploads/logo.PNG', 'image/png', $cap)->convert)->toBeTrue();
});

it('skips conversion for non-images, already-webp, or unsupported servers', function () {
    $cap  = new ImageCapability(true, false, true, false);
    $none = new ImageCapability(false, false, false, false);

    expect(ConversionPlan::for('/x/a.gif', 'image/gif', $cap)->convert)->toBeFalse()
        ->and(ConversionPlan::for('/x/a.webp', 'image/webp', $cap)->convert)->toBeFalse()
        ->and(ConversionPlan::for('/x/a.jpg', 'image/jpeg', $none)->convert)->toBeFalse();
});

it('renders a picture with a webp source and a lazy, async img fallback', function () {
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_attr')->returnArg();

    $html = (new PictureRenderer())->render([
        'src'  => 'https://x/photo.jpg',
        'webp' => 'https://x/photo.webp',
        'alt'  => 'A photo',
    ]);

    expect($html)->toContain('<picture>')
        ->and($html)->toContain('<source type="image/webp" srcset="https://x/photo.webp"')
        ->and($html)->toContain('alt="A photo"')
        ->and($html)->toContain('loading="lazy"')
        ->and($html)->toContain('decoding="async"');
});

it('renders an LCP image eager with high priority and not lazy', function () {
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_attr')->returnArg();

    $html = (new PictureRenderer())->render(['src' => 'https://x/hero.jpg', 'webp' => 'https://x/hero.webp', 'alt' => 'Hero', 'lcp' => true]);

    expect($html)->toContain('fetchpriority="high"')
        ->and($html)->not->toContain('loading="lazy"');
});

it('degrades to a plain img with no webp variant, and stays valid with an empty alt', function () {
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_attr')->returnArg();

    $html = (new PictureRenderer())->render(['src' => 'https://x/p.jpg', 'alt' => '']);

    expect($html)->not->toContain('<picture>')
        ->and($html)->toContain('<img ')
        ->and($html)->toContain('alt=""');
});

it('emits a srcset + sizes when responsive widths are present', function () {
    Functions\when('esc_url')->returnArg();
    Functions\when('esc_attr')->returnArg();

    $html = (new PictureRenderer())->render([
        'src'    => 'https://x/p.jpg',
        'alt'    => 'P',
        'srcset' => 'https://x/p-300.jpg 300w, https://x/p-800.jpg 800w',
        'sizes'  => '(max-width: 800px) 100vw, 800px',
    ]);

    expect($html)->toContain('srcset="https://x/p-300.jpg 300w, https://x/p-800.jpg 800w"')
        ->and($html)->toContain('sizes="(max-width: 800px) 100vw, 800px"');
});
