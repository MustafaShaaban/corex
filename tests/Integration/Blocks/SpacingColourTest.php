<?php

/**
 * A margin, a padding, a background colour and a text colour set on a section block reach the page,
 * through WordPress's own render (spec 136, User Story 1 scenario 6, FR-007 and FR-008). That the
 * chosen text colour then reaches the block's headings and text is a matter of its style sheet, and
 * is proved over the compiled sheets in `tests/block-spacing-colour.test.js`, and in a browser in
 * `tests/e2e/block-colour.spec.js`.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Environment\SiteEnvironment;
use Corex\Tests\Support\SavedOption;

/** The block's root element in its printed markup, or null when it printed none. */
function spacingColourTestRoot(string $html): ?WP_HTML_Tag_Processor
{
    $tags = new WP_HTML_Tag_Processor($html);

    return $tags->next_tag() ? $tags : null;
}

/**
 * The block comment of a block with a margin on top, an equal padding on each axis, and a
 * background and a text colour, all CoreX theme presets.
 *
 * @param array<string,mixed> $attributes What the block needs to print something.
 */
function spacingColourTestComment(string $name, array $attributes): string
{
    $set = [
        'backgroundColor' => 'ink',
        'textColor' => 'surface',
        'style' => ['spacing' => [
            'margin' => ['top' => 'var:preset|spacing|50'],
            'padding' => [
                'top' => 'var:preset|spacing|60',
                'bottom' => 'var:preset|spacing|60',
                'left' => 'var:preset|spacing|40',
                'right' => 'var:preset|spacing|40',
            ],
        ]],
    ];

    return sprintf('<!-- wp:%s %s /-->', $name, wp_json_encode($attributes + $set, JSON_UNESCAPED_SLASHES));
}

/** @return array<string,array{0:string,1:array<string,mixed>}> */
function spacingColourTestBlocks(): array
{
    return [
        'hero' => ['corex/hero', ['title' => 'T']],
        'stat' => ['corex/stat', ['value' => '1']],
    ];
}

it('US1 scenario 6, FR-007, FR-008: prints the chosen colours and spacing on the root', function (string $name, array $attributes) {
    $root = spacingColourTestRoot(do_blocks(spacingColourTestComment($name, $attributes)));
    $style = (string) $root?->get_attribute('style');

    expect($root?->has_class('corex-' . substr($name, 6)))->toBeTrue('the block drew its own root')
        ->and($root?->has_class('has-ink-background-color'))->toBeTrue()
        ->and($root?->has_class('has-background'))->toBeTrue()
        ->and($root?->has_class('has-surface-color'))->toBeTrue()
        ->and($root?->has_class('has-text-color'))->toBeTrue()
        ->and($style)->toContain('margin-top:var(--wp--preset--spacing--50)')
        ->and($style)->toContain('padding-top:var(--wp--preset--spacing--60)')
        ->and($style)->toContain('padding-bottom:var(--wp--preset--spacing--60)')
        ->and($style)->toContain('padding-left:var(--wp--preset--spacing--40)')
        ->and($style)->toContain('padding-right:var(--wp--preset--spacing--40)');
})->with(spacingColourTestBlocks());

it('FR-007, FR-008: a block with nothing set prints no added style or colour class', function (string $name, array $attributes) {
    $root = spacingColourTestRoot(do_blocks(sprintf('<!-- wp:%s %s /-->', $name, wp_json_encode($attributes))));
    $colourClasses = array_filter(
        explode(' ', (string) $root?->get_attribute('class')),
        static fn (string $class): bool => str_starts_with($class, 'has-'),
    );

    expect($root)->not->toBeNull()
        ->and($root?->get_attribute('style'))->toBeNull()
        ->and($colourClasses)->toBe([]);
})->with(spacingColourTestBlocks());

it('FR-007: every CoreX block a person can insert supports a top and bottom margin, as WordPress has it registered', function () {
    $registry = WP_Block_Type_Registry::get_instance();
    $missing = [];
    foreach ($registry->get_all_registered() as $name => $type) {
        if (! str_starts_with($name, 'corex/') || ($type->supports['inserter'] ?? true) === false) {
            continue;
        }
        if (($type->supports['spacing']['margin'] ?? null) !== ['top', 'bottom']) {
            $missing[] = $name;
        }
    }

    expect($missing)->toBe([])
        ->and($registry->is_registered('corex/account'))->toBeTrue('the account block, registered in code, is one of them');
});

it('FR-003, FR-007: puts a registered form\'s margin on the form, not on the test-site notice printed before it', function () {
    $mode = get_option(SiteEnvironment::MODE_OPTION, null);
    update_option(SiteEnvironment::MODE_OPTION, SiteEnvironment::STAGING, false);

    try {
        $html = do_blocks('<!-- wp:corex/form {"formSlug":"contact","style":{"spacing":{"margin":{"top":"var:preset|spacing|50"}}}} /-->');
    } finally {
        SavedOption::restore(SiteEnvironment::MODE_OPTION, $mode);
    }
    $first = spacingColourTestRoot($html);
    $form = new WP_HTML_Tag_Processor($html);
    while ($form->next_tag() && ! $form->has_class('corex-form')) {
        // Walk on to the form: the root `corex.root` names.
    }

    expect($first?->has_class('corex-flow-notice'))->toBeTrue('the markup starts with the test-site notice')
        ->and($first?->get_attribute('style'))->toBeNull()
        ->and($form->get_tag())->toBe('FORM')
        ->and((string) $form->get_attribute('style'))->toContain('margin-top:var(--wp--preset--spacing--50)');
})->skip(
    fn (): bool => ! class_exists(\Corex\Forms\Block\NoticePanel::class),
    'this release line has no notice panel before a form',
);

it('FR-008: the root of each block that takes colour carries the class its colour rules are written on, beside the colour classes', function () {
    // The sheets write their rules on `.wp-block-corex-<slug>.has-text-color`, the class the editor's
    // wrapper carries too, so a preview drawn inside the wrapper takes them (FR-006a).
    $minimal = [
        'accordion'         => ['items' => [['title' => 'Q', 'content' => 'A']]],
        'carousel'          => ['slides' => [['content' => 'S']]],
        'cta'               => ['title' => 'T'],
        'gallery'           => ['images' => [['url' => 'https://example.test/a.jpg', 'alt' => 'A']]],
        'hero'              => ['title' => 'T'],
        'jobs'              => [],
        'newsletter-signup' => [],
        'posts'             => [],
        'pricing'           => ['plan' => 'P'],
        'projects'          => [],
        'stat'              => ['value' => '1'],
        'tabs'              => ['tabs' => [['label' => 'One', 'content' => 'C']]],
        'team'              => ['members' => [['name' => 'N']]],
        'testimonial'       => ['quote' => 'Q'],
    ];
    $missed = [];
    foreach ($minimal as $slug => $attributes) {
        $html = do_blocks(sprintf('<!-- wp:corex/%s %s /-->', $slug, wp_json_encode($attributes + ['textColor' => 'surface'], JSON_UNESCAPED_SLASHES)));
        $root = spacingColourTestRoot($html);
        if (! $root?->has_class('wp-block-corex-' . $slug) || ! $root->has_class('has-text-color')) {
            $missed[$slug] = substr($html, 0, 160);
        }
    }

    expect($missed)->toBe([]);
});
