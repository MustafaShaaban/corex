<?php

/**
 * Unit tests for the reset executor's page arms (spec 041 FR-008, spec 115): a kit-created page is deleted;
 * a page the owner had is restored from its record, emptied, or kept, and never deleted; the reading
 * settings are put back. WP functions stubbed via Brain Monkey.
 *
 * @package Corex\Tests\Unit\Cli
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Cli\Reset\ResetAction;
use Corex\Cli\Reset\ResetExecutor;
use Corex\Provisioning\PageRecord;

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

it('keeps a replaced page even when it is asked to remove it', function () {
    Functions\when('get_post_meta')->justReturn('replaced');
    Functions\when('delete_post_meta')->justReturn(true);

    Functions\expect('wp_delete_post')->never();
    Functions\expect('wp_update_post')->never();

    (new ResetExecutor())->apply(new ResetAction(ResetAction::REMOVE_DEMO, '31', 'Remove page #31'));
});

it('restores a page from its record, with its template, and untracks it', function () {
    $record = ['title' => 'About us', 'content' => '<p>Ours</p>', 'template' => 'page-wide', 'status' => 'draft'];
    Functions\when('get_post_meta')->justReturn($record);
    Functions\when('wp_slash')->returnArg();

    Functions\expect('wp_update_post')->once()->with([
        'ID'           => 40,
        'post_title'   => 'About us',
        'post_content' => '<p>Ours</p>',
        'post_status'  => 'draft',
    ])->andReturn(40);
    Functions\expect('update_post_meta')->once()->with(40, PageRecord::TEMPLATE_META, 'page-wide')->andReturn(true);
    // The recorded markup goes back unfiltered, and the filters come back after.
    Functions\when('has_filter')->justReturn(10);
    Functions\expect('kses_remove_filters')->once();
    Functions\expect('kses_init_filters')->once();
    Functions\expect('delete_post_meta')->twice()->andReturn(true);
    Functions\expect('wp_delete_post')->never();

    (new ResetExecutor())->apply(new ResetAction(ResetAction::RESTORE_PAGE, '40', 'restore'));
});

it('keeps a page whose record cannot be read instead of guessing', function () {
    Functions\when('get_post_meta')->justReturn('');
    Functions\expect('delete_post_meta')->twice()->andReturn(true);
    Functions\expect('wp_update_post')->never();
    Functions\expect('wp_delete_post')->never();

    (new ResetExecutor())->apply(new ResetAction(ResetAction::RESTORE_PAGE, '41', 'restore'));
});

it('puts the recorded front-page and posts-page settings back', function () {
    Functions\when('get_option')->justReturn(['show_on_front' => 'page', 'page_on_front' => '12', 'page_for_posts' => '']);
    Functions\when('get_post')->justReturn(Mockery::mock(WP_Post::class));

    $written = [];
    Functions\when('update_option')->alias(function (string $key, $value) use (&$written): bool {
        $written[$key] = $value;

        return true;
    });
    Functions\expect('delete_option')->once()->with('page_for_posts')->andReturn(true);

    (new ResetExecutor())->apply(new ResetAction(ResetAction::RESTORE_READING, 'reading', 'reading'));

    expect($written)->toBe(['show_on_front' => 'page', 'page_on_front' => '12']);
});

it('shows the latest posts when the recorded front page is gone', function () {
    Functions\when('get_option')->justReturn(['show_on_front' => 'page', 'page_on_front' => '12', 'page_for_posts' => '0']);
    Functions\when('get_post')->justReturn(null);

    $written = [];
    Functions\when('update_option')->alias(function (string $key, $value) use (&$written): bool {
        $written[$key] = $value;

        return true;
    });
    Functions\expect('delete_option')->once()->with('page_on_front')->andReturn(true);

    (new ResetExecutor())->apply(new ResetAction(ResetAction::RESTORE_READING, 'reading', 'reading'));

    expect($written['show_on_front'])->toBe('posts');
});
