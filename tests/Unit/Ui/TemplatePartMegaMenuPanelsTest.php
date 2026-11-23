<?php

/**
 * Where a mega menu item's panel comes from (spec 137 slice 3; FR-010, FR-012; DECISIONS #691).
 *
 * A panel is a template part of the `mega-menu` area in the active theme, drawn by WordPress's own
 * template part block, so a translation plugin that translates a part translates a panel. A part
 * that does not exist, belongs to another area or holds no block is no panel. A panel that is already
 * being drawn (a panel that holds the menu it belongs to) is not drawn again.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\MegaMenuArea;
use Corex\Ui\Blocks\TemplatePartMegaMenuPanels;

/** A template part as WordPress's `get_block_template()` returns one. */
function megaMenuPanelsPart(string $area, string $content): object
{
    return (object) ['area' => $area, 'content' => $content];
}

beforeEach(function () {
    $this->parts = [
        'corex//services' => megaMenuPanelsPart(MegaMenuArea::AREA, '<!-- wp:paragraph --><p>Consulting</p><!-- /wp:paragraph -->'),
        'corex//footer' => megaMenuPanelsPart('footer', '<!-- wp:paragraph --><p>Footer</p><!-- /wp:paragraph -->'),
        'corex//blank' => megaMenuPanelsPart(MegaMenuArea::AREA, "  \n "),
    ];
    $this->drawn = [];

    Functions\when('get_stylesheet')->justReturn('corex');
    Functions\when('get_block_template')->alias(fn (string $id, string $type) => $type === 'wp_template_part' ? ($this->parts[$id] ?? null) : null);
    Functions\when('has_blocks')->alias(fn ($content) => str_contains((string) $content, '<!-- wp:'));
    Functions\when('render_block')->alias(function (array $block): string {
        $this->drawn[] = $block;

        return '<div class="wp-block-template-part">' . $block['attrs']['slug'] . '</div>';
    });

    $this->panels = new TemplatePartMegaMenuPanels();
});

it('FR-010: draws a part of the mega-menu area with WordPress\'s template part block', function () {
    expect($this->panels->render('services'))->toBe('<div class="wp-block-template-part">services</div>')
        ->and($this->drawn)->toHaveCount(1)
        ->and($this->drawn[0]['blockName'])->toBe('core/template-part')
        ->and($this->drawn[0]['attrs'])->toMatchArray(['slug' => 'services', 'theme' => 'corex', 'tagName' => 'div']);
});

it('FR-012: draws nothing for a part that does not exist, belongs to another area or holds no block', function (string $slug) {
    expect($this->panels->render($slug))->toBe('')
        ->and($this->drawn)->toBe([]);
})->with([
    'missing' => ['deleted'],
    'another area' => ['footer'],
    'empty' => ['blank'],
    'no slug' => [''],
]);

it('FR-012: draws nothing for a panel already being drawn, and draws it again once it is done', function () {
    $inner = null;
    Functions\when('render_block')->alias(function (array $block) use (&$inner): string {
        // The panel holds the menu it belongs to: the item inside asks for the same panel.
        $inner = $this->panels->render('services');

        return '<div class="wp-block-template-part">outer</div>';
    });

    expect($this->panels->render('services'))->toBe('<div class="wp-block-template-part">outer</div>')
        ->and($inner)->toBe('')
        ->and($this->panels->render('services'))->toBe('<div class="wp-block-template-part">outer</div>');
});

it('FR-012: lets the panel be drawn again after a draw that failed', function () {
    Functions\when('render_block')->alias(function (): string {
        throw new RuntimeException('a block failed');
    });

    expect(fn () => $this->panels->render('services'))->toThrow(RuntimeException::class);

    Functions\when('render_block')->justReturn('<div>again</div>');

    expect($this->panels->render('services'))->toBe('<div>again</div>');
});

it('FR-012: draws nothing when WordPress draws the part as nothing', function () {
    Functions\when('render_block')->justReturn("  \n");

    expect($this->panels->render('services'))->toBe('');
});
