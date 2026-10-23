<?php

/**
 * Unit tests for sample content (spec 115, US-SAFE-14, FR-012): a block marked `is-corex-sample` is
 * drawn for nobody but people who can edit, who see it labelled; every other block passes through.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\SampleBlocks;

function sampleBlocks(): SampleBlocks
{
    return new SampleBlocks('/plugins/corex-blocks/corex-blocks.php', '1.0.0');
}

beforeEach(function () {
    Functions\when('__')->returnArg();
    Functions\when('esc_html')->returnArg();
    Functions\when('plugins_url')->justReturn('https://acme.test/sample.css');
});

it('shows a visitor nothing of a sample block', function () {
    Functions\when('current_user_can')->justReturn(false);
    Functions\expect('wp_enqueue_style')->never();

    $html = sampleBlocks()->render('<section>98% Client satisfaction</section>', [
        'blockName' => 'core/group',
        'attrs'     => ['className' => 'alignfull is-corex-sample'],
    ]);

    expect($html)->toBe('');
});

it('shows someone who can edit the sample with its label in front of it', function () {
    Functions\when('current_user_can')->alias(fn (string $cap): bool => $cap === 'edit_posts');
    Functions\expect('wp_enqueue_style')->once();

    $html = sampleBlocks()->render('<section>98%</section>', [
        'blockName' => 'core/group',
        'attrs'     => ['className' => 'is-corex-sample'],
    ]);

    expect($html)->toBe('<p class="corex-sample-label" role="note">Sample: replace before publishing</p><section>98%</section>');
});

it('leaves blocks that are not samples as they are, whoever looks', function (array $attrs) {
    Functions\when('current_user_can')->justReturn(false);

    expect(sampleBlocks()->render('<p>Ours</p>', ['blockName' => 'core/group', 'attrs' => $attrs]))->toBe('<p>Ours</p>');
})->with([
    'no class'            => [[]],
    'other classes'       => [['className' => 'alignfull corex-section']],
    'a class that only contains the word' => [['className' => 'is-corex-sample-list']],
]);
