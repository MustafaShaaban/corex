<?php

/**
 * Unit tests for the new corex/* component block renderers (spec 027: FR-003/004/005/006).
 * WordPress escaping is stubbed at the boundary (returnArg), as in UiBlocksTest.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Corex\Ui\Blocks\AccordionRenderer;
use Corex\Ui\Blocks\PricingRenderer;
use Corex\Ui\Blocks\StatRenderer;
use Corex\Ui\Blocks\TestimonialRenderer;
use Brain\Monkey\Functions;

beforeEach(function () {
    Functions\when('esc_html')->returnArg();
    Functions\when('esc_html__')->returnArg();
    Functions\when('esc_attr')->returnArg();
    Functions\when('esc_url')->returnArg();
});

$block = (object) [];

it('renders a stat with value and label, and nothing when both are empty', function () use ($block) {
    $html = (new StatRenderer())->render(['value' => '98%', 'label' => 'Uptime', 'description' => 'last year'], '', $block);

    expect($html)->toContain('class="corex-stat"')
        ->toContain('corex-stat__value">98%')
        ->toContain('corex-stat__label">Uptime')
        ->toContain('corex-stat__desc">last year')
        ->and((new StatRenderer())->render([], '', $block))->toBe('');
});

it('renders a testimonial as a figure/blockquote/figcaption, empty quote -> nothing', function () use ($block) {
    $html = (new TestimonialRenderer())->render(['quote' => 'Great framework.', 'author' => 'Sam', 'role' => 'CTO'], '', $block);

    expect($html)->toContain('<figure class="corex-testimonial">')
        ->toContain('<blockquote class="corex-testimonial__quote">Great framework.')
        ->toContain('<figcaption')->toContain('Sam, CTO')
        ->and((new TestimonialRenderer())->render(['author' => 'Sam'], '', $block))->toBe('');
});

it('renders a pricing card with feature list and a CTA only when text+url are set', function () use ($block) {
    $html = (new PricingRenderer())->render([
        'plan'     => 'Pro',
        'price'    => '$29',
        'period'   => '/mo',
        'features' => "Unlimited\nPriority support\n",
        'ctaText'  => 'Buy',
        'ctaUrl'   => 'https://example.com/buy',
    ], '', $block);

    expect($html)->toContain('corex-pricing__plan">Pro')
        ->toContain('corex-pricing__price">$29')
        ->toContain('corex-pricing__period">/mo')
        ->toContain('<ul class="corex-pricing__features"><li>Unlimited</li><li>Priority support</li></ul>')
        ->toContain('<a class="corex-pricing__cta" href="https://example.com/buy">Buy</a>');

    // CTA omitted when the URL is missing.
    expect((new PricingRenderer())->render(['plan' => 'Free', 'ctaText' => 'Go'], '', $block))
        ->not->toContain('corex-pricing__cta');
});

it('renders accordion items as native details/summary, empty -> nothing', function () use ($block) {
    $html = (new AccordionRenderer())->render([
        'items' => "What is Corex? | A WordPress framework.\nIs it free? | Yes.\nTitle only\n",
    ], '', $block);

    expect($html)->toContain('<div class="corex-accordion">')
        ->toContain('<details class="corex-accordion__item"><summary class="corex-accordion__summary">What is Corex?</summary><div class="corex-accordion__content">A WordPress framework.</div></details>')
        ->toContain('<summary class="corex-accordion__summary">Title only</summary>')
        ->and((new AccordionRenderer())->render(['items' => ''], '', $block))->toBe('');
});

it('uses no hardcoded colors or pixel sizes in any rendered markup (token-only)', function () use ($block) {
    $markup = (new StatRenderer())->render(['value' => '1', 'label' => 'x'], '', $block)
        . (new TestimonialRenderer())->render(['quote' => 'q', 'author' => 'a'], '', $block)
        . (new PricingRenderer())->render(['plan' => 'P', 'price' => '$1', 'features' => "a\nb", 'ctaText' => 'Go', 'ctaUrl' => 'https://e.com'], '', $block)
        . (new AccordionRenderer())->render(['items' => "T | C"], '', $block);

    expect($markup)->not->toMatch('/#[0-9a-fA-F]{3,6}\b/')   // no hex colors
        ->not->toMatch('/\b\d+px\b/')                          // no px sizes
        ->not->toMatch('/style\s*=/');                         // no inline styles
});
