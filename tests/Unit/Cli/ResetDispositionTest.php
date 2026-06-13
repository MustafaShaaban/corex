<?php

/**
 * Unit tests for the reset disposition branch (spec 041 FR-008): a kit-created page is deleted; a kit-adopted
 * pre-existing page is only emptied + untracked, never deleted. WP functions stubbed via Brain Monkey.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\Reset\ResetAction;
use Corex\Cli\Reset\ResetExecutor;

it('deletes a kit-created page on reset', function () {
    Functions\when('get_post_meta')->justReturn('created');
    Functions\when('get_option')->justReturn(0);

    Functions\expect('wp_delete_post')->once()->with(777, true)->andReturn((object) ['ID' => 777]);
    Functions\expect('wp_update_post')->never();

    (new ResetExecutor())->apply(new ResetAction(ResetAction::REMOVE_DEMO, '777', 'Remove page #777'));
});

it('empties and untracks a kit-adopted page without deleting it', function () {
    Functions\when('get_post_meta')->justReturn('adopted');

    Functions\expect('wp_update_post')->once()->with(['ID' => 2511, 'post_content' => ''])->andReturn(2511);
    Functions\expect('delete_post_meta')->once()->with(2511, '_corex_kit_page')->andReturn(true);
    Functions\expect('wp_delete_post')->never();

    (new ResetExecutor())->apply(new ResetAction(ResetAction::REMOVE_DEMO, '2511', 'Remove page #2511'));
});
