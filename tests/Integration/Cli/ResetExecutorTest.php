<?php

/**
 * Integration test: the reset executor's soft arms against the real ./wp install
 * (spec 025 US1: FR-002/003/004). Only the non-destructive actions are exercised — the
 * gated `db-wipe` arm is never run against the dev database.
 *
 * @package Corex\Tests\Integration\Cli
 */

declare(strict_types=1);

use Corex\Cli\Reset\ResetAction;
use Corex\Cli\Reset\ResetExecutor;

it('deletes a corex_* option', function () {
    add_option('corex_test_reset_opt', 'value');

    (new ResetExecutor())->apply(new ResetAction(ResetAction::DELETE_OPTION, 'corex_test_reset_opt', 'delete'));

    expect(get_option('corex_test_reset_opt'))->toBeFalse();
});

it('removes a seeded demo page and reverts the static front page', function () {
    $pageId = wp_insert_post([
        'post_title'  => 'Reset Test Home',
        'post_status' => 'publish',
        'post_type'   => 'page',
    ]);
    update_option('show_on_front', 'page');
    update_option('page_on_front', $pageId);
    update_option('corex_setup_demo_seeded', '1');

    (new ResetExecutor())->apply(new ResetAction(ResetAction::REMOVE_DEMO, (string) $pageId, 'remove'));

    expect(get_post($pageId))->toBeNull()
        ->and(get_option('show_on_front'))->toBe('posts')
        ->and((int) get_option('page_on_front'))->toBe(0);

    delete_option('corex_setup_demo_seeded'); // housekeeping
});
