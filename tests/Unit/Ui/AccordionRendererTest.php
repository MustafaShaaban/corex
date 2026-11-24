<?php

/**
 * The accordion and its items, drawn (spec 136 slice 5, User Story 4; FR-030, FR-031, FR-032, FR-039).
 *
 * An accordion that holds accordion items prints its root around what they drew. One saved in the old
 * shape (its questions and answers in `items`, no inner blocks) is drawn from that attribute in the new
 * markup, with the same questions and answers. An item draws a native `details`: its question in the
 * `summary`, as a heading of the accordion's level or as plain text, then the kit's chevron, then its
 * answer. "Open one at a time" is the `name` of `details`, the same for every item of one accordion.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\AccordionGroups;
use Corex\Ui\Blocks\AccordionItemMarkup;
use Corex\Ui\Blocks\AccordionItemRenderer;
use Corex\Ui\Blocks\AccordionRenderer;

beforeEach(function () {
    Functions\when('esc_attr')->returnArg();
    Functions\when('wp_kses_post')->returnArg();
    $this->ids = 0;
    Functions\when('wp_unique_id')->alias(fn (string $prefix = ''): string => $prefix . (string) ++$this->ids);
});

/** What the item markup draws for a question, as a string to compare. */
function accordionItemHtml(string $question, string $answer = '', string $tag = 'span', string $name = ''): string
{
    return '<details class="corex-accordion__item"' . ($name === '' ? '' : ' name="' . $name . '"') . '>'
        . '<summary class="corex-accordion__summary"><' . $tag . ' class="corex-accordion__question">' . $question . '</' . $tag . '>'
        . AccordionItemMarkup::CHEVRON . '</summary>'
        . ($answer === '' ? '' : '<div class="corex-accordion__content">' . $answer . '</div>')
        . '</details>';
}

/** A block as WordPress passes it, with inner blocks and context. */
function accordionBlock(array $innerBlocks = [], array $context = []): object
{
    return (object) ['inner_blocks' => $innerBlocks, 'context' => $context];
}

it("FR-032: the kit's chevron is an icon hidden from assistive technology, painted in the text colour", function () {
    expect(AccordionItemMarkup::CHEVRON)->toContain('class="corex-accordion__icon"')
        ->toContain('aria-hidden="true"')
        ->toContain('focusable="false"')
        ->toContain('stroke="currentColor"')
        ->not->toMatch('/\b\d+px\b/');
});

it('FR-030: an item draws its question in the summary and its answer after it', function () {
    $html = (new AccordionItemRenderer(new AccordionItemMarkup()))
        ->render(['question' => 'What is CoreX?'], '<p>A framework.</p>', accordionBlock());

    expect($html)->toBe(accordionItemHtml('What is CoreX?', '<p>A framework.</p>'));
});

it('FR-030: an item with no question prints nothing; one with no answer prints no answer', function () {
    $renderer = new AccordionItemRenderer(new AccordionItemMarkup());

    expect($renderer->render(['question' => '  '], '<p>A</p>', accordionBlock()))->toBe('')
        ->and($renderer->render([], '<p>A</p>', accordionBlock()))->toBe('')
        ->and($renderer->render(['question' => 'Q'], "\n", accordionBlock()))->toBe(accordionItemHtml('Q'));
});

it('FR-030: an answer made only of empty paragraphs is no answer, so the item opens to no empty strip', function (string $answer) {
    $renderer = new AccordionItemRenderer(new AccordionItemMarkup());

    expect($renderer->render(['question' => 'Q'], $answer, accordionBlock()))->toBe(accordionItemHtml('Q'));
})->with([
    'an empty paragraph' => ['<p></p>'],
    'one with a class, around new lines' => ["\n<p class=\"wp-block-paragraph\"></p>\n"],
    'spaces and a no-break space' => ['<p> &nbsp; </p><p>&#160;</p>'],
    'a no-break space as a character' => ["<p>\u{00A0}</p>"],
    'a line break alone' => ['<p><br></p>'],
]);

it('FR-030: an answer with text in any paragraph, or another block, is kept', function () {
    $renderer = new AccordionItemRenderer(new AccordionItemMarkup());

    expect($renderer->render(['question' => 'Q'], '<p></p><p>Yes.</p>', accordionBlock()))
        ->toBe(accordionItemHtml('Q', '<p></p><p>Yes.</p>'))
        ->and($renderer->render(['question' => 'Q'], '<p></p><figure><img src="a.jpg" alt="A"></figure>', accordionBlock()))
        ->toBe(accordionItemHtml('Q', '<p></p><figure><img src="a.jpg" alt="A"></figure>'));
});

it("FR-032: an item's question is a heading of the accordion's level, plain text by default or out of range", function () {
    $renderer = new AccordionItemRenderer(new AccordionItemMarkup());
    $at = static fn (mixed $level): string => $renderer->render(
        ['question' => 'Q'],
        '',
        accordionBlock([], ['corex/accordionHeadingLevel' => $level]),
    );

    expect($at(3))->toBe(accordionItemHtml('Q', '', 'h3'))
        ->and($at('6'))->toBe(accordionItemHtml('Q', '', 'h6'))
        ->and($at(0))->toBe(accordionItemHtml('Q'))
        ->and($at(1))->toBe(accordionItemHtml('Q'))
        ->and($at(7))->toBe(accordionItemHtml('Q'));
});

it('FR-031: an item takes the name its accordion gives it, so one opens at a time', function () {
    $html = (new AccordionItemRenderer(new AccordionItemMarkup()))
        ->render(['question' => 'Q'], '', accordionBlock([], ['corex/accordionName' => 'corex-accordion-4']));

    expect($html)->toBe(accordionItemHtml('Q', '', 'span', 'corex-accordion-4'));
});

it('FR-030: an accordion that holds items prints its root around what they drew', function () {
    $renderer = new AccordionRenderer(new AccordionItemMarkup());
    $items = accordionItemHtml('Q1', '<p>A1</p>') . "\n" . accordionItemHtml('Q2', '<p>A2</p>');

    expect($renderer->render([], $items, accordionBlock([(object) [], (object) []])))
        ->toBe('<div class="corex-accordion">' . $items . '</div>')
        // Items that all drew nothing (none has a question): the accordion prints nothing.
        ->and($renderer->render([], "\n\n", accordionBlock([(object) []])))->toBe('');
});

it('FR-039: an accordion saved in the old shape is drawn from its items in the new markup, keeping every question and answer', function () {
    $html = (new AccordionRenderer(new AccordionItemMarkup()))->render([
        'items' => [
            ['title' => 'What is Corex?', 'content' => 'A WordPress framework.'],
            ['title' => 'Title only', 'content' => ''],
            ['title' => '', 'content' => 'An answer with no question is skipped, as before.'],
        ],
    ], '', accordionBlock());

    expect($html)->toBe(
        '<div class="corex-accordion">'
        . accordionItemHtml('What is Corex?', 'A WordPress framework.')
        . accordionItemHtml('Title only')
        . '</div>'
    );
});

it('FR-039: an old accordion with nothing in it prints nothing', function () {
    $renderer = new AccordionRenderer(new AccordionItemMarkup());

    expect($renderer->render(['items' => []], '', accordionBlock()))->toBe('')
        ->and($renderer->render([], '', accordionBlock()))->toBe('');
});

it('FR-039: an old accordion saved as delimited text still renders (the shape before spec 029)', function () {
    $html = (new AccordionRenderer(new AccordionItemMarkup()))->render(['items' => "Q | A\nTitle only"], '', accordionBlock());

    expect($html)->toBe('<div class="corex-accordion">' . accordionItemHtml('Q', 'A') . accordionItemHtml('Title only') . '</div>');
});

it('FR-031, FR-032: an old accordion takes the heading level and one name for all its items', function () {
    $html = (new AccordionRenderer(new AccordionItemMarkup()))->render([
        'items' => [['title' => 'Q1', 'content' => 'A1'], ['title' => 'Q2', 'content' => 'A2']],
        'oneOpen' => true,
        'headingLevel' => 4,
    ], '', accordionBlock());

    expect($html)->toBe(
        '<div class="corex-accordion">'
        . accordionItemHtml('Q1', 'A1', 'h4', 'corex-accordion-1')
        . accordionItemHtml('Q2', 'A2', 'h4', 'corex-accordion-1')
        . '</div>'
    );
});

it('FR-031: an accordion with "Open one at a time" gives each of its items the same name, another accordion another', function () {
    $groups = new AccordionGroups();
    $first = (object) ['name' => 'corex/accordion', 'attributes' => ['oneOpen' => true]];
    $second = (object) ['name' => 'corex/accordion', 'attributes' => ['oneOpen' => true]];
    $item = ['blockName' => 'corex/accordion-item'];

    $a = $groups->context([], $item, $first)[AccordionGroups::CONTEXT];
    $b = $groups->context(['postId' => 7], $item, $first);
    $c = $groups->context([], $item, $second)[AccordionGroups::CONTEXT];

    expect($a)->toBe('corex-accordion-1')
        ->and($b)->toBe(['postId' => 7, AccordionGroups::CONTEXT => 'corex-accordion-1'])
        ->and($c)->toBe('corex-accordion-2');
});

it('FR-031: an item of an accordion with the setting off has no name, even inside one that has it on', function () {
    $groups = new AccordionGroups();
    $off = (object) ['name' => 'corex/accordion', 'attributes' => ['oneOpen' => false]];

    // An outer accordion's name reaches its descendants as block context; an inner one turns it off.
    expect($groups->context([AccordionGroups::CONTEXT => 'corex-accordion-9'], ['blockName' => 'corex/accordion-item'], $off))
        ->toBe([AccordionGroups::CONTEXT => '']);
});

it('FR-031: leaves the context of every other block as it is', function () {
    $groups = new AccordionGroups();
    $accordion = (object) ['name' => 'corex/accordion', 'attributes' => ['oneOpen' => true]];
    $group = (object) ['name' => 'core/group', 'attributes' => []];

    expect($groups->context(['postId' => 1], ['blockName' => 'core/paragraph'], $accordion))->toBe(['postId' => 1])
        ->and($groups->context(['postId' => 1], ['blockName' => 'corex/accordion-item'], $group))->toBe(['postId' => 1])
        ->and($groups->context(['postId' => 1], ['blockName' => 'corex/accordion-item'], null))->toBe(['postId' => 1]);
});
