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

/**
 * What a fixture may say the page needs to draw the block (`needs`): a current post, or a mega menu
 * panel, a template part of the `mega-menu` area named by the fixture's `panel` attribute (spec 137).
 */
const PARITY_TEST_KNOWN_NEEDS = ['post', 'panel'];

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
    $this->posts = CreatedPosts::watch('post', 'wp_template_part');
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

    // A fixture that could not fail (no tree, a wrong prefix, a pageOnly class of the block) fails here.
    expect(ParityTree::fixtureProblems($name, $fixture))->toBe([])
        ->and(array_diff($needs, PARITY_TEST_KNOWN_NEEDS))->toBe([], $name . ': parity.json needs what no test can give')
        ->and(WP_Block_Type_Registry::get_instance()->is_registered($name))->toBeTrue($name . ' is not registered');

    if (in_array('post', $needs, true)) {
        $postId = wp_insert_post(['post_title' => 'Parity probe', 'post_status' => 'publish', 'post_type' => 'post']);
        $GLOBALS['post'] = get_post($postId);
    }

    if (in_array('panel', $needs, true)) {
        $partId = wp_insert_post([
            'post_type' => 'wp_template_part',
            'post_name' => (string) ($fixture['attributes']['panel'] ?? ''),
            'post_title' => 'Parity panel',
            'post_status' => 'publish',
            'post_content' => '<!-- wp:paragraph --><p>Panel</p><!-- /wp:paragraph -->',
        ]);
        wp_set_post_terms($partId, get_stylesheet(), 'wp_theme');
        wp_set_post_terms($partId, 'mega-menu', 'wp_template_part_area');
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
