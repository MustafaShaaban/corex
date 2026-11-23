<?php

/**
 * The PHP parity helper computes the tree the parity contract compares (spec 136, FR-011;
 * DECISIONS #671), the same tree as its JavaScript twin in `packages/build-tools/parity/tree.js`.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Corex\Tests\Support\ParityTree;

it('FR-011: a class of the block is the prefix, or the prefix followed by __ or --', function () {
    $result = ParityTree::fromMarkup(
        '<section class="corex-hero corex-hero--split corex-heroes corex-hero-x wp-block-corex-hero">'
        . '<h2 class="corex-hero__title">T</h2></section>',
        'corex-hero',
    );

    expect($result['tree'])->toBe([
        [
            'tag' => 'section',
            'classes' => ['corex-hero', 'corex-hero--split'],
            'children' => [['tag' => 'h2', 'classes' => ['corex-hero__title']]],
        ],
    ]);
});

it('FR-011: sorts the block classes', function () {
    $tree = ParityTree::fromMarkup('<div class="corex-hero--split corex-hero"></div>', 'corex-hero')['tree'];

    expect($tree[0]['classes'])->toBe(['corex-hero', 'corex-hero--split']);
});

it('FR-011: an element without a class of the block is transparent, its children keep their nesting', function () {
    $tree = ParityTree::fromMarkup(
        '<section class="corex-hero"><div class="layout"><span><p class="corex-hero__eyebrow">E</p></span>'
        . '<div><a class="corex-hero__cta">Go</a></div></div></section><p class="unrelated">x</p>',
        'corex-hero',
    )['tree'];

    expect($tree)->toBe([
        [
            'tag' => 'section',
            'classes' => ['corex-hero'],
            'children' => [
                ['tag' => 'p', 'classes' => ['corex-hero__eyebrow']],
                ['tag' => 'a', 'classes' => ['corex-hero__cta']],
            ],
        ],
    ]);
});

it('FR-011: keeps the nesting after a picture, whose source element the parser does not know as void', function () {
    $tree = ParityTree::fromMarkup(
        '<section class="corex-hero"><picture><source class="corex-hero__source" srcset="a.webp" type="image/webp">'
        . '<img class="corex-hero__bg" src="a.jpg" alt="" /></picture>'
        . '<div class="corex-hero__inner"></div></section>',
        'corex-hero',
    )['tree'];

    expect($tree)->toBe([
        [
            'tag' => 'section',
            'classes' => ['corex-hero'],
            'children' => [
                ['tag' => 'source', 'classes' => ['corex-hero__source']],
                ['tag' => 'img', 'classes' => ['corex-hero__bg']],
                ['tag' => 'div', 'classes' => ['corex-hero__inner']],
            ],
        ],
    ]);
});

it('FR-011: skips an element marked data-corex-editor-only and everything inside it', function () {
    $result = ParityTree::fromMarkup(
        '<section class="corex-hero"><div data-corex-editor-only><button type="button">Remove</button></div></section>',
        'corex-hero',
    );

    expect($result['errors'])->toBe([])
        ->and($result['tree'])->toBe([['tag' => 'section', 'classes' => ['corex-hero']]]);
});

it('FR-011: reports an editor-only element that carries a class of the block, or holds one', function () {
    $result = ParityTree::fromMarkup(
        '<section class="corex-hero"><span data-corex-editor-only class="corex-hero__cta">Go</span>'
        . '<div data-corex-editor-only><p class="corex-hero__eyebrow">E</p></div></section>',
        'corex-hero',
    );

    expect($result['tree'])->toBe([['tag' => 'section', 'classes' => ['corex-hero']]])
        ->and($result['errors'])->toBe([
            '<span> is editor-only (data-corex-editor-only) and carries the block class corex-hero__cta',
            '<p> is editor-only (data-corex-editor-only) and carries the block class corex-hero__eyebrow',
        ]);
});

it('FR-011: removes the elements named in pageOnly, with what they hold', function () {
    $tree = ParityTree::fromMarkup(
        '<form class="corex-hero"><div class="corex-hero__trap"><input class="corex-hero__field" /></div>'
        . '<button class="corex-hero__submit">Send</button></form>',
        'corex-hero',
        ['corex-hero__trap'],
    )['tree'];

    expect($tree)->toBe([
        [
            'tag' => 'form',
            'classes' => ['corex-hero'],
            'children' => [['tag' => 'button', 'classes' => ['corex-hero__submit']]],
        ],
    ]);
});

it('FR-011: markup with no element of the block gives an empty tree', function () {
    expect(ParityTree::fromMarkup('', 'corex-hero')['tree'])->toBe([])
        ->and(ParityTree::fromMarkup('<p>Text</p>', 'corex-hero')['tree'])->toBe([]);
});

it('FR-011: formats a tree as an outline, one element per line', function () {
    $tree = ParityTree::fromMarkup(
        '<section class="corex-hero"><div class="corex-hero__inner"><h2 class="corex-hero__title">T</h2></div>'
        . '<img class="corex-hero__bg" /></section>',
        'corex-hero',
    )['tree'];

    expect(ParityTree::format($tree))->toBe(implode("\n", [
        'section.corex-hero',
        '  div.corex-hero__inner',
        '    h2.corex-hero__title',
        '  img.corex-hero__bg',
    ]))->and(ParityTree::format([]))->toBe('(empty)');
});

it('FR-012: a well-formed fixture has no problem', function () {
    expect(ParityTree::fixtureProblems('corex/hero', [
        'prefix' => 'corex-hero',
        'pageOnly' => ['corex-trap'],
        'tree' => [['tag' => 'div', 'classes' => ['corex-hero']]],
    ]))->toBe([]);
});

it('FR-012: a fixture with no tree fails, naming the block', function () {
    $message = 'corex/hero: parity.json has no tree, so the comparison proves nothing';

    expect(ParityTree::fixtureProblems('corex/hero', ['prefix' => 'corex-hero']))->toBe([$message])
        ->and(ParityTree::fixtureProblems('corex/hero', ['prefix' => 'corex-hero', 'tree' => []]))->toBe([$message]);
});

it('FR-012: a fixture whose first root does not carry the prefix fails, naming the block', function () {
    expect(ParityTree::fixtureProblems('corex/hero', [
        'prefix' => 'corex-heros',
        'tree' => [['tag' => 'div', 'classes' => ['corex-hero']]],
    ]))->toBe(['corex/hero: the first root of parity.json does not carry the prefix class corex-heros'])
        ->and(ParityTree::fixtureProblems('corex/hero', [
            'prefix' => 'corex-hero',
            'tree' => [['tag' => 'div', 'classes' => ['corex-hero__inner']]],
        ]))->toBe(['corex/hero: the first root of parity.json does not carry the prefix class corex-hero']);
});

it('FR-012: a pageOnly class that is a class of the block fails, naming the block', function () {
    expect(ParityTree::fixtureProblems('corex/hero', [
        'prefix' => 'corex-hero',
        'pageOnly' => ['corex-hero', 'corex-hero__trap', 'corex-hero--x', 'trap'],
        'tree' => [['tag' => 'div', 'classes' => ['corex-hero']]],
    ]))->toBe(['corex/hero: pageOnly may not name a class of the block: corex-hero, corex-hero__trap, corex-hero--x']);
});

it('FR-011: normalizes a fixture tree into the same shape', function () {
    expect(ParityTree::normalize([['children' => [], 'classes' => ['corex-hero--split', 'corex-hero'], 'tag' => 'SECTION']]))
        ->toBe([['tag' => 'section', 'classes' => ['corex-hero', 'corex-hero--split']]]);
});
