<?php

/**
 * The page draws what the editor draws (spec 136, User Story 2, FR-012, SC-003; DECISIONS #671).
 *
 * Every `parity.json` of the framework, found by search under `plugins/` and `addons/`, is drawn
 * through WordPress (`render_block` with a parsed block), so the renderer comes from the container
 * with its real collaborators, the wrapper attributes are merged and inner blocks are real blocks.
 * The tree of the elements that carry a class of the block is compared with the fixture's. Where the
 * block holds blocks, what each child block draws is marked (`data-corex-parity-child`) and left out:
 * the element that holds them is compared here, and the children through their own fixtures. A
 * fixture's `context` is the block context a parent gives a child block. The same fixtures are drawn
 * by the block editor in `tests/block-editor-parity.test.js`.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Multisite\PluginActivationInspector;
use Corex\Tests\Support\BlockFiles;
use Corex\Tests\Support\CreatedPosts;
use Corex\Tests\Support\ParityTree;
use Corex\Tests\Support\ThemeContract;

/**
 * What a fixture may say the page needs to draw the block (`needs`): a current post, or a plug-in
 * that must be active (`plugin:<folder>/<file>.php`). The twin of `isKnownNeed()` in
 * `@corex/build-tools/parity`.
 */
const PARITY_TEST_KNOWN_NEEDS = ['post'];
const PARITY_TEST_PLUGIN_NEED = '#^plugin:([^/\s]+/[^/\s]+\.php)$#';

/**
 * @param list<mixed> $needs A fixture's `needs`.
 * @return list<mixed> The ones no test can give.
 */
function parityTestUnknownNeeds(array $needs): array
{
    return array_values(array_filter(
        $needs,
        static fn (mixed $need): bool => ! in_array($need, PARITY_TEST_KNOWN_NEEDS, true)
            && preg_match(PARITY_TEST_PLUGIN_NEED, (string) $need) !== 1,
    ));
}

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

/**
 * Draws a fixture's block through WordPress: its `context` given to the block itself, and each child
 * block's markup wrapped in an element marked `data-corex-parity-child`, so the tree leaves it out.
 *
 * @param array<string,mixed> $block   the parsed block
 * @param array<string,mixed> $context the fixture's `context`
 */
function parityTestRender(array $block, array $context): string
{
    $child = static function (array $parsed, array $source, ?WP_Block $parent): array {
        if ($parent !== null) {
            $parsed['corexParityChild'] = true;
        }

        return $parsed;
    };
    $mark = static fn (string $html, array $parsed): string => ! empty($parsed['corexParityChild'])
        ? '<div ' . ParityTree::CHILD_BLOCK . '>' . $html . '</div>'
        : $html;
    $give = static fn (array $current, array $parsed, ?WP_Block $parent): array => $parent === null
        ? array_merge($current, $context)
        : $current;

    add_filter('render_block_data', $child, 10, 3);
    add_filter('render_block', $mark, 10, 2);
    add_filter('render_block_context', $give, 10, 3);

    try {
        return render_block($block);
    } finally {
        remove_filter('render_block_data', $child, 10);
        remove_filter('render_block', $mark, 10);
        remove_filter('render_block_context', $give, 10);
    }
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

    // A fixture that could not fail (no tree, a wrong prefix, a pageOnly class of the block) fails here.
    expect(ParityTree::fixtureProblems($name, $fixture, $metadata))->toBe([])
        ->and(parityTestUnknownNeeds($needs))->toBe([], $name . ': parity.json needs what no test can give')
        ->and(WP_Block_Type_Registry::get_instance()->is_registered($name))->toBeTrue($name . ' is not registered');

    // A plug-in the fixture needs must be active, or the page draws another tree (a note that the
    // feature is unavailable) and the comparison would fail for a reason it does not name.
    $inspector = Boot::app()->container()->make(PluginActivationInspector::class);
    foreach ($needs as $need) {
        if (preg_match(PARITY_TEST_PLUGIN_NEED, (string) $need, $plugin) === 1) {
            expect($inspector->isActive($plugin[1]))->toBeTrue($name . ': parity.json needs the plug-in ' . $plugin[1] . ' active');
        }
    }

    if (in_array('post', $needs, true)) {
        $postId = wp_insert_post(['post_title' => 'Parity probe', 'post_status' => 'publish', 'post_type' => 'post']);
        $GLOBALS['post'] = get_post($postId);
    }

    $inner = parityTestParsedBlocks($fixture['innerBlocks'] ?? []);
    $markup = parityTestRender([
        'blockName' => $name,
        'attrs' => is_array($fixture['attributes'] ?? null) ? $fixture['attributes'] : [],
        'innerBlocks' => $inner,
        'innerHTML' => '',
        'innerContent' => array_fill(0, count($inner), null),
    ], is_array($fixture['context'] ?? null) ? $fixture['context'] : []);
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
