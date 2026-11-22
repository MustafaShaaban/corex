<?php

/**
 * The page draws what the editor draws (spec 136, User Story 2, FR-012, SC-003; DECISIONS #671).
 *
 * Every `parity.json` of the framework, found by search under `plugins/` and `addons/`, is drawn
 * through WordPress (`render_block` with a parsed block), so the renderer comes from the container
 * with its real collaborators, the wrapper attributes are merged and inner blocks are real blocks.
 * The tree of the elements that carry a class of the block is compared with the fixture's. The same
 * fixtures are drawn by the block editor in `tests/block-editor-parity.test.js`.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Tests\Support\BlockFiles;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\ParityTree;
use Corex\Tests\Support\ThemeContract;

/** What a fixture may say the page needs to draw the block (`needs`). */
const PARITY_TEST_KNOWN_NEEDS = ['post'];

/**
 * Every fixture, keyed by its block's name.
 *
 * @return array<string,array{0:string}>
 */
function parityTestFixtures(): array
{
    $root = ThemeContract::root();
    $fixtures = [];
    foreach (BlockFiles::find([$root . '/plugins', $root . '/addons'], 'parity.json') as $file) {
        $metadata = json_decode((string) file_get_contents(dirname($file) . '/block.json'), true);
        $fixtures[(string) ($metadata['name'] ?? $file)] = [$file];
    }

    return $fixtures;
}

/**
 * A fixture's `innerBlocks` as parsed blocks: each `{name, attributes, innerHTML, innerBlocks}`.
 *
 * @param mixed $blocks
 * @return list<array<string,mixed>>
 */
function parityTestParsedBlocks(mixed $blocks): array
{
    $parsed = [];
    foreach (is_array($blocks) ? $blocks : [] as $block) {
        $inner = parityTestParsedBlocks($block['innerBlocks'] ?? []);
        $html = (string) ($block['innerHTML'] ?? '');
        $parsed[] = [
            'blockName' => (string) ($block['name'] ?? ''),
            'attrs' => is_array($block['attributes'] ?? null) ? $block['attributes'] : [],
            'innerBlocks' => $inner,
            'innerHTML' => $html,
            'innerContent' => $inner === [] ? [$html] : array_fill(0, count($inner), null),
        ];
    }

    return $parsed;
}

beforeEach(function () {
    $this->posts = CreatedPosts::watch('post');
    $this->globalPost = $GLOBALS['post'] ?? null;
});

afterEach(function () {
    $GLOBALS['post'] = $this->globalPost;
    $this->posts->delete();
});

it('FR-012: finds the framework fixtures by search', function () {
    expect(parityTestFixtures())->not->toBe([]);
});

it('FR-012: draws the tree of its parity.json on the page', function (string $file) {
    $fixture = json_decode((string) file_get_contents($file), true);
    $metadata = json_decode((string) file_get_contents(dirname($file) . '/block.json'), true);
    $name = (string) $metadata['name'];
    $needs = is_array($fixture['needs'] ?? null) ? $fixture['needs'] : [];

    expect(array_diff($needs, PARITY_TEST_KNOWN_NEEDS))->toBe([], $name . ': parity.json needs what no test can give')
        ->and(WP_Block_Type_Registry::get_instance()->is_registered($name))->toBeTrue($name . ' is not registered');

    if (in_array('post', $needs, true)) {
        $postId = wp_insert_post(['post_title' => 'Parity probe', 'post_status' => 'publish', 'post_type' => 'post']);
        $GLOBALS['post'] = get_post($postId);
    }

    $inner = parityTestParsedBlocks($fixture['innerBlocks'] ?? []);
    $markup = render_block([
        'blockName' => $name,
        'attrs' => is_array($fixture['attributes'] ?? null) ? $fixture['attributes'] : [],
        'innerBlocks' => $inner,
        'innerHTML' => '',
        'innerContent' => array_fill(0, count($inner), null),
    ]);
    $page = ParityTree::fromMarkup($markup, (string) $fixture['prefix'], $fixture['pageOnly'] ?? []);
    $expected = ParityTree::normalize($fixture['tree'] ?? []);

    $message = implode("\n", [
        $name . ': the page does not draw the tree of its parity.json.',
        ...$page['errors'],
        'Page:',
        ParityTree::format($page['tree']),
        'parity.json:',
        ParityTree::format($expected),
        'Page markup:',
        $markup,
    ]);

    expect($page['errors'])->toBe([], $message)
        ->and($page['tree'])->toBe($expected, $message);
})->with(fn (): array => parityTestFixtures());
