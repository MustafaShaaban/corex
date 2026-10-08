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

use Corex\Boot;
use Corex\Forms\Form;
use Corex\Forms\FormRegistry;

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

// Spec 104, US1 (#248). Through the block as WordPress renders it, with WordPress's own escaping.
it('renders a registered form with the wording it states', function () {
    Boot::app()->container()->make(FormRegistry::class)->register(new class extends Form {
        public string $slug = 'corex-wording-probe';

        protected array $fields = ['phone' => ['type' => 'text', 'rules' => ['required']]];

        public function submitLabel(): string
        {
            return 'Request a call';
        }

        public function successMessage(): string
        {
            return 'We will call you <today>.';
        }
    });

    $html = do_blocks('<!-- wp:corex/form {"formSlug":"corex-wording-probe"} /-->');

    expect($html)->toContain('<button type="submit" class="corex-form__submit">Request a call</button>')
        ->and($html)->toContain('data-corex-success="We will call you &lt;today&gt;."')
        // It states no general error, so that one is CoreX's.
        ->and($html)->toContain('data-corex-error="Please review the highlighted fields and try again."');
});
