<?php

/**
 * What `corex/form` prints with no form chosen, and what a block saved earlier keeps printing
 * (spec 136, FR-020, User Story 2 scenario 4; DECISIONS #673).
 *
 * The editor asks for a form when the block names none, and a block inserted from the inserter
 * starts with `formSlug` empty. Such a block prints nothing on the page. A block saved earlier
 * with no attribute keeps block.json's default, the contact form, and prints it byte for byte as
 * its renderer draws it, so pages saved before the chooser do not change.
 *
 * @package Corex\Tests\Integration\Forms
 */

declare(strict_types=1);

use Corex\Boot;
use Corex\Forms\Block\FormBlockRenderer;

beforeEach(function () {
    $this->globalPost = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = null;
});

afterEach(function () {
    $GLOBALS['post'] = $this->globalPost;
});

it('FR-020: a form block that names no form prints nothing', function () {
    expect(do_blocks('<!-- wp:corex/form {"formSlug":""} /-->'))->toBe('');
});

it('FR-020: a form block saved with no attribute still prints the contact form, its bytes unchanged', function () {
    $comment = '<!-- wp:corex/form /-->';
    $block = new WP_Block(parse_blocks($comment)[0]);
    $raw = Boot::app()->container()->make(FormBlockRenderer::class)->render($block->attributes, '', $block);
    $html = do_blocks($comment);

    expect($block->attributes['formSlug'] ?? null)->toBe('contact')
        ->and($raw)->toContain('<form')
        ->and($html)->toBe($raw);
});
