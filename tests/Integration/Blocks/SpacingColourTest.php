<?php

/**
 * A margin, a padding, a background colour and a text colour set on a section block reach the page,
 * through WordPress's own render (spec 136, User Story 1 scenario 6, FR-007 and FR-008). That the
 * chosen text colour then reaches the block's headings and text is a matter of its style sheet, and
 * is proved over the compiled sheets in `tests/block-spacing-colour.test.js`.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

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
