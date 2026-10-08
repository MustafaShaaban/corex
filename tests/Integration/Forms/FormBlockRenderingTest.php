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
use Corex\Forms\Block\FormParts;
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

// Spec 104, US3 (#248). A form's own markup, through the block and WordPress's own escaping.
it('shows a form its own markup, and withholds one that is missing a part', function () {
    $registry = Boot::app()->container()->make(FormRegistry::class);
    $registry->register(new class extends Form {
        public string $slug = 'corex-drawn-probe';

        protected array $fields = ['phone' => ['type' => 'phone', 'label' => 'Phone', 'rules' => ['required']]];

        public function markup(FormParts $parts): ?string
        {
            return '<form ' . $parts->attributes(['class' => 'lead-card']) . '>'
                . '<div ' . $parts->fieldAttributes('phone') . '>'
                . $parts->label('phone') . '<input type="tel" ' . $parts->control('phone') . ' />' . $parts->error('phone')
                . '</div>' . $parts->hidden() . $parts->submit() . $parts->status() . '</form>';
        }
    });
    $registry->register(new class extends Form {
        public string $slug = 'corex-broken-probe';

        protected array $fields = ['phone' => ['type' => 'text']];

        public function markup(FormParts $parts): ?string
        {
            return '<form ' . $parts->attributes() . '>' . $parts->field('phone') . '</form>';
        }
    });
    // WordPress reports a developer's mistake as a PHP notice. Here it is read, not raised.
    $reported = [];
    add_filter('doing_it_wrong_trigger_error', '__return_false');
    add_action('doing_it_wrong_run', function (string $where, string $what) use (&$reported): void {
        $reported[] = $what;
    }, 10, 2);
    wp_set_current_user(0);

    $drawn  = do_blocks('<!-- wp:corex/form {"formSlug":"corex-drawn-probe"} /-->');
    $broken = do_blocks('<!-- wp:corex/form {"formSlug":"corex-broken-probe"} /-->');

    remove_filter('doing_it_wrong_trigger_error', '__return_false');
    remove_all_actions('doing_it_wrong_run');

    expect($drawn)->toContain('<form class="corex-form lead-card" method="post" novalidate')
        ->and($drawn)->toContain('data-corex-endpoint="' . esc_url(rest_url('corex/v1/forms/corex-drawn-probe')) . '"')
        ->and($drawn)->toContain('<input type="tel" id="corex-corex-drawn-probe-phone" name="phone"')
        ->and($drawn)->toContain('name="corex_hp"')
        ->and($broken)->toBe('')
        ->and($reported)->toHaveCount(1)
        ->and($reported[0])->toContain('hidden(), status()');
});
