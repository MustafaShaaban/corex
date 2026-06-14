<?php

/**
 * Unit tests for the DLS block-style registrar (spec 054, US3). The styles are appearance-only
 * variants on core blocks (research D4) — delivered via register_block_style(), not new blocks.
 *
 * @package Corex\Tests\Unit\Ui
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Ui\Blocks\BlockStyles;

beforeEach(function () {
    Functions\when('__')->returnArg();
    $this->styles = new BlockStyles('https://example.test/wp-content/plugins/corex-ui/assets/block-styles.css');
});

it('declares the six DLS block styles on their core blocks', function () {
    $names = array_column($this->styles->styles(), 'name');

    expect($names)->toEqual([
        'corex-card', 'corex-section', 'corex-empty', 'corex-striped', 'corex-secondary', 'corex-ghost',
    ]);

    foreach ($this->styles->styles() as $style) {
        expect($style['block'])->toStartWith('core/')
            ->and($style['label'])->not->toBe('');
    }
});

it('registers each style with WordPress against its block + a shared stylesheet handle', function () {
    Functions\expect('wp_register_style')->once()->with(
        BlockStyles::HANDLE,
        \Mockery::type('string'),
        [],
        \Mockery::any(),
    );

    Functions\expect('register_block_style')->times(6)->with(
        \Mockery::type('string'),
        \Mockery::on(static fn ($args): bool => isset($args['name'], $args['label'])
            && $args['style_handle'] === BlockStyles::HANDLE),
    );

    if (! defined('COREX_UI_VERSION')) {
        define('COREX_UI_VERSION', 'test');
    }

    $this->styles->register();
});
