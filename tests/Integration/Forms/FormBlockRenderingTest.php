<?php

/**
 * Regression: the corex/form block renders a registered formSlug form on the real front end
 * (spec 068 FR-013/FR-015). block.json declares `flowId`/`flowSlug` defaults, so the renderer must
 * route to the flow renderer only when a flow is actually referenced — otherwise a legacy formSlug
 * form (e.g. the Contact form used by the `corex/contact` pattern) silently rendered nothing.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

it('renders the registered contact form for a formSlug block despite the flow defaults', function () {
    expect(WP_Block_Type_Registry::get_instance()->is_registered('corex/form'))->toBeTrue();

    // do_blocks merges the block.json attribute defaults (source=flow, flowId=0, flowSlug="").
    $html = do_blocks('<!-- wp:corex/form {"formSlug":"contact"} /-->');

    expect($html)->toContain('<form')
        ->and($html)->toContain('data-corex-schema=')
        ->and($html)->toContain('data-corex-form="contact"')
        ->and($html)->toContain('name="corex_hp"');
});

it('renders the contact form through the registered corex/contact pattern', function () {
    $pattern = WP_Block_Patterns_Registry::get_instance()->get_registered('corex/contact');

    expect($pattern)->not->toBeNull()
        ->and(do_blocks($pattern['content']))->toContain('data-corex-schema=');
});

it('renders nothing (non-fatal) for a flow block that references an unknown flow', function () {
    $html = do_blocks('<!-- wp:corex/form {"source":"flow","flowId":999999} /-->');

    expect($html)->toBe('');
});
