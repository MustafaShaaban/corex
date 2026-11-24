<?php

/**
 * The accordion through WordPress (spec 136 slice 5, User Story 4; FR-030, FR-031, FR-032, FR-039,
 * FR-040; TC-BLK-02-01 to 03 and 06 as far as markup reaches; the browser cases are in
 * `tests/e2e/accordion.spec.js`).
 *
 * The new shape, an accordion holding accordion items whose answers hold any blocks, and the old
 * shape, saved before this slice with its questions and answers in `items`, are both drawn by
 * `do_blocks`, so the renderers come from the container, the block context reaches the items and the
 * wrapper attributes are merged.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Ui\Patterns\PatternLibrary;

/** An accordion item in the saved shape: its question, and its answer's blocks. */
function accordionTestItem(string $question, string $answer, array $extra = []): string
{
    return sprintf(
        '<!-- wp:corex/accordion-item %s -->%s<!-- /wp:corex/accordion-item -->',
        wp_json_encode(['question' => $question] + $extra, JSON_UNESCAPED_SLASHES),
        $answer,
    );
}

/** An accordion in the saved shape, holding the given items. */
function accordionTestBlock(string $items, array $attributes = []): string
{
    return sprintf(
        '<!-- wp:corex/accordion %s -->%s<!-- /wp:corex/accordion -->',
        $attributes === [] ? '' : wp_json_encode($attributes, JSON_UNESCAPED_SLASHES),
        $items,
    );
}

function accordionTestParagraph(string $text): string
{
    return '<!-- wp:paragraph --><p>' . $text . '</p><!-- /wp:paragraph -->';
}

/**
 * Every `details` of the markup, in order, each with its attributes and the tag of its question.
 *
 * @return list<array{class:string,name:?string,id:?string,question:string}>
 */
function accordionTestItems(string $html): array
{
    $items = [];
    $tags = new WP_HTML_Tag_Processor($html);
    while ($tags->next_tag()) {
        if ($tags->get_tag() === 'DETAILS') {
            $items[] = [
                'class' => (string) $tags->get_attribute('class'),
                'name' => $tags->get_attribute('name'),
                'id' => $tags->get_attribute('id'),
                'question' => '',
            ];
        } elseif ($tags->has_class('corex-accordion__question') && $items !== []) {
            $items[array_key_last($items)]['question'] = $tags->get_tag();
        }
    }

    return $items;
}

it('FR-030, TC-BLK-02-01: an answer holds any blocks: a list, an image and a button render inside it', function () {
    $answer = accordionTestParagraph('Three ways in.')
        . '<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>One</li><!-- /wp:list-item --></ul><!-- /wp:list -->'
        . '<!-- wp:image --><figure class="wp-block-image"><img src="https://example.test/a.jpg" alt="A diagram"/></figure><!-- /wp:image -->'
        . '<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="https://example.test/start">Start</a></div><!-- /wp:button --></div><!-- /wp:buttons -->';
    $html = do_blocks(accordionTestBlock(
        accordionTestItem('How do I start?', $answer) . accordionTestItem('Is it free?', accordionTestParagraph('Yes.')),
    ));

    // The first item's answer: from its content to the end of its details.
    $answerHtml = substr($html, (int) strpos($html, 'corex-accordion__content'));
    $answerHtml = substr($answerHtml, 0, (int) strpos($answerHtml, '</details>'));

    expect($html)->toStartWith('<div class="corex-accordion wp-block-corex-accordion"')
        ->and(accordionTestItems($html))->toHaveCount(2)
        ->and($html)->toContain('<summary class="corex-accordion__summary"><span class="corex-accordion__question">How do I start?</span>')
        ->and($answerHtml)->toContain('<li>One</li>')
        ->toContain('alt="A diagram"')
        ->toContain('class="wp-block-button__link wp-element-button" href="https://example.test/start"');
});

it("FR-030: an item's root carries its own class, its anchor and its parent's prefix", function () {
    $html = do_blocks(accordionTestBlock(accordionTestItem('Q', accordionTestParagraph('A'), ['anchor' => 'pricing-question'])));
    [$item] = accordionTestItems($html);

    expect($item['id'])->toBe('pricing-question')
        ->and(explode(' ', $item['class']))->toContain('corex-accordion__item', 'wp-block-corex-accordion-item');
});

it('FR-030: an item with no question is skipped on the page, and an accordion of such items prints nothing', function () {
    $html = do_blocks(accordionTestBlock(accordionTestItem('', accordionTestParagraph('Orphan answer')) . accordionTestItem('Kept', accordionTestParagraph('A'))));

    expect(accordionTestItems($html))->toHaveCount(1)
        ->and($html)->not->toContain('Orphan answer')
        ->and(do_blocks(accordionTestBlock(accordionTestItem('', accordionTestParagraph('A')))))->toBe('');
});

it('FR-031, TC-BLK-02-03: with "Open one at a time" off, the default, no item has a name, so several stay open', function () {
    $items = accordionTestItems(do_blocks(accordionTestBlock(accordionTestItem('Q1', '') . accordionTestItem('Q2', ''))));

    expect(array_column($items, 'name'))->toBe([null, null]);
});

it('FR-031, TC-BLK-02-02: with it on, the items of one accordion share a name, and another accordion has its own', function () {
    $two = accordionTestItem('Q1', accordionTestParagraph('A1')) . accordionTestItem('Q2', accordionTestParagraph('A2'));
    $html = do_blocks(accordionTestBlock($two, ['oneOpen' => true]) . accordionTestBlock($two, ['oneOpen' => true]));
    $names = array_column(accordionTestItems($html), 'name');

    expect($names)->toHaveCount(4)
        ->and($names[0])->toStartWith('corex-accordion-')
        ->and($names[1])->toBe($names[0])
        ->and($names[2])->toBe($names[3])
        ->and($names[2])->not->toBe($names[0]);
});

it('FR-031: an accordion inside an answer works on its own', function () {
    $inner = accordionTestBlock(accordionTestItem('Inner 1', '') . accordionTestItem('Inner 2', ''));
    $html = do_blocks(accordionTestBlock(accordionTestItem('Outer 1', $inner) . accordionTestItem('Outer 2', ''), ['oneOpen' => true]));
    $names = array_column(accordionTestItems($html), 'name');

    // Document order: Outer 1, Inner 1, Inner 2, Outer 2.
    expect($names[0])->toStartWith('corex-accordion-')
        ->and($names[3])->toBe($names[0])
        ->and([$names[1], $names[2]])->toBe([null, null]);
});

it('FR-032: the questions are headings of the chosen level, inside the summary, beside the chevron', function () {
    $html = do_blocks(accordionTestBlock(accordionTestItem('Q1', '') . accordionTestItem('Q2', ''), ['headingLevel' => 3]));

    expect(array_column(accordionTestItems($html), 'question'))->toBe(['H3', 'H3'])
        ->and($html)->toContain('<summary class="corex-accordion__summary"><h3 class="corex-accordion__question">Q1</h3><svg class="corex-accordion__icon"')
        ->and(array_column(accordionTestItems(do_blocks(accordionTestBlock(accordionTestItem('Q', ''))))  , 'question'))->toBe(['SPAN']);
});

it('FR-039, TC-BLK-02-06: an accordion saved before the change keeps its questions and answers', function () {
    // As a page saved before this slice holds it: a self-closing block, its items in the attribute.
    $saved = '<!-- wp:corex/accordion {"items":[{"title":"What is Corex?","content":"A WordPress <strong>framework</strong>."},'
        . '{"title":"Title only","content":""}]} /-->';
    $html = do_blocks($saved);
    $items = accordionTestItems($html);

    expect($html)->toStartWith('<div class="corex-accordion wp-block-corex-accordion"')
        ->and($items)->toHaveCount(2)
        ->and(array_column($items, 'question'))->toBe(['SPAN', 'SPAN'])
        ->and($html)->toContain('<span class="corex-accordion__question">What is Corex?</span>')
        ->toContain('<div class="corex-accordion__content">A WordPress <strong>framework</strong>.</div>')
        ->toContain('<span class="corex-accordion__question">Title only</span>' . \Corex\Ui\Blocks\AccordionItemMarkup::CHEVRON . '</summary></details>');
});

it('FR-040: the FAQ pattern inserts an accordion with items, so it shows questions', function () {
    $faq = array_column((new PatternLibrary())->patterns(), 'content', 'name')['corex/faq'];
    $administrators = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID']);
    wp_set_current_user((int) ($administrators[0] ?? 0));

    try {
        $html = do_blocks($faq);
    } finally {
        wp_set_current_user(0);
    }
    $items = accordionTestItems($html);

    expect(parse_blocks($faq)[0]['innerBlocks'][1]['blockName'] ?? null)->toBe('corex/accordion')
        ->and($items)->toHaveCount(3)
        ->and(array_column($items, 'question'))->toBe(['H3', 'H3', 'H3'])
        ->and(substr_count($html, 'class="corex-accordion__content"'))->toBe(3)
        ->and($html)->toContain('Sample: replace before publishing');
});

it("FR-040: the FAQ pattern's questions are sample content: a visitor sees its heading and no question, as before", function () {
    wp_set_current_user(0);
    $html = do_blocks(array_column((new PatternLibrary())->patterns(), 'content', 'name')['corex/faq']);

    expect($html)->toContain('Frequently asked questions')
        ->and(accordionTestItems($html))->toBe([])
        ->and($html)->not->toContain('corex-accordion');
});
