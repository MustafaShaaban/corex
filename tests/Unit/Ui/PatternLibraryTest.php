<?php

/**
 * Unit tests for the Corex section patterns (spec 009 US1: FR-001, FR-003, FR-004, SC-002).
 *
 * Every pattern is token-only (no raw hex/px), carries accessible structure, and the
 * contact pattern composes the corex/form block.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Patterns\PatternLibrary;

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html__')->returnArg();
});

it('provides the section patterns under the Corex catalog', function () {
    $names = array_column((new PatternLibrary())->patterns(), 'name');

    expect($names)->toContain('corex/hero', 'corex/features', 'corex/cta', 'corex/testimonial', 'corex/contact');
});

it('uses no hardcoded colors or sizes in any pattern (token-only)', function () {
    foreach ((new PatternLibrary())->patterns() as $pattern) {
        expect($pattern['content'])
            ->not->toMatch('/#[0-9a-fA-F]{3,6}\b/')  // no hex colors
            ->not->toMatch('/\b\d+px\b/');            // no pixel sizes
    }
});

it('gives every pattern a heading and composes the contact form in the contact pattern', function () {
    $byName = [];
    foreach ((new PatternLibrary())->patterns() as $pattern) {
        $byName[$pattern['name']] = $pattern['content'];
    }

    expect($byName['corex/hero'])->toContain('wp:heading')->toContain('"level":1')
        ->and($byName['corex/features'])->toContain('wp:heading')
        ->and($byName['corex/contact'])->toContain('wp:corex/form');
});

it('includes the new 054 section patterns', function () {
    $names = array_column((new PatternLibrary())->patterns(), 'name');

    expect($names)->toContain('corex/section-header', 'corex/content-split', 'corex/stats', 'corex/faq', 'corex/news');
});

it('composes only real registered corex blocks (no pattern drift)', function () {
    $blocksDir = dirname(__DIR__, 3) . '/addons/corex-ui/src/Blocks';
    $real      = [];
    foreach (glob($blocksDir . '/*/block.json') ?: [] as $file) {
        $json = json_decode((string) file_get_contents($file), true);
        if (is_array($json) && isset($json['name'])) {
            $real[] = (string) $json['name'];
        }
    }

    foreach ((new PatternLibrary())->patterns() as $pattern) {
        preg_match_all('#wp:(corex/[a-z-]+)#', $pattern['content'], $matches);
        foreach ($matches[1] as $blockName) {
            if ($blockName === 'corex/form') {
                continue; // corex-forms block, not a corex-ui block
            }
            expect($real)->toContain($blockName);
        }
    }
});

/**
 * The first block of a kind in a pattern: its comment attributes and the opening tag it saves.
 *
 * @return array{attributes:array<string,mixed>,tag:string,wrapper:string} the inner link stands for a button's tag
 */
function patternBlock(string $content, string $block): array
{
    preg_match('~<!-- wp:' . preg_quote($block, '~') . '(?: (\{.*?\}))? -->\s*(<[a-z0-9]+[^>]*>)(?:\s*(<a [^>]*>))?~', $content, $match);

    return [
        'attributes' => (array) json_decode($match[1] ?? '{}', true),
        'tag'        => ($match[3] ?? '') !== '' ? $match[3] : ($match[2] ?? ''),
        'wrapper'    => $match[2] ?? '',
    ];
}

/** @return list<string> the classes of an opening tag */
function tagClasses(string $tag): array
{
    preg_match('/class="([^"]*)"/', $tag, $match);

    return array_values(array_filter(explode(' ', $match[1] ?? '')));
}

// Spec 134, FR-015 (DECISIONS #640): the hero is a dark band and the call-to-action a brass band.
it('draws the hero as a dark band and the call-to-action as a brass band (FR-015)', function (string $name, string $style) {
    $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')[$name];
    $group = patternBlock($content, 'group');

    expect($group['attributes']['className'] ?? '')->toBe('is-style-' . $style)
        ->and(tagClasses($group['tag']))->toContain('is-style-' . $style);
})->with([
    'hero' => ['corex/hero', 'corex-band-dark'],
    'call to action' => ['corex/cta', 'corex-band-brass'],
]);

it('names no colour for the section, its heading or its button, so the band draws them (FR-015)', function (string $name) {
    $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')[$name];

    foreach (['group', 'heading', 'button'] as $block) {
        $found = patternBlock($content, $block);
        $attributes = $found['attributes'];

        expect(array_intersect_key($attributes, array_flip(['backgroundColor', 'textColor', 'gradient'])))->toBe([], "{$name}: the {$block} names a colour")
            ->and($attributes['style']['color'] ?? [])->toBe([], "{$name}: the {$block} has a colour of its own")
            ->and(preg_grep('/^has-(?:.+-)?(?:color|background-color|text-color|background)$/', tagClasses($found['tag'])))->toBe([], "{$name}: the {$block}'s saved HTML carries a colour class")
            ->and($found['tag'])->not->toContain('color:');
    }
})->with(['corex/hero', 'corex/cta']);

it('keeps the hero and call-to-action buttons action links, with the markup the button block saves (FR-015)', function (string $name) {
    $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')[$name];
    $button = patternBlock($content, 'button');

    expect($button['attributes'])->toBe(['className' => PatternLibrary::ACTION_LINK])
        ->and(tagClasses($button['wrapper']))->toBe(['wp-block-button', PatternLibrary::ACTION_LINK])
        ->and(tagClasses($button['tag']))->toBe(['wp-block-button__link', 'wp-element-button']);
})->with(['corex/hero', 'corex/cta']);

it('saves each block\'s comment classes in its HTML, so the editor accepts the hero and the call to action', function (string $name) {
    $content = array_column((new PatternLibrary())->patterns(), 'content', 'name')[$name];

    preg_match_all('~<!-- wp:([a-z/-]+) (\{.*?\}) -->\s*(<[a-z0-9]+[^>]*>)~', $content, $blocks, PREG_SET_ORDER);
    expect($blocks)->not->toBeEmpty();

    foreach ($blocks as [, $block, $json, $tag]) {
        $attributes = (array) json_decode($json, true);
        $classes = tagClasses($tag);

        if (isset($attributes['className'])) {
            expect($classes)->toContain(...explode(' ', (string) $attributes['className']));
        }
        if (isset($attributes['align']) && $block === 'group') {
            expect($classes)->toContain('align' . $attributes['align']);
        }
        if (isset($attributes['textAlign'])) {
            expect($classes)->toContain('has-text-align-' . $attributes['textAlign']);
        }
    }
})->with(['corex/hero', 'corex/cta']);
