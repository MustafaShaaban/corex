<?php

/**
 * The newsletter signup block's editor script is told whether signup is available, in the block
 * editor (spec 136, FR-018): the editor draws the block in place, and with the newsletter add-on
 * off the page prints the renderer's note, so the editor must know to draw it.
 *
 * @package Corex\Tests\Integration\Blocks
 */

declare(strict_types=1);

use Corex\Blocks\NewsletterSignupRenderer;
use Corex\Boot;

it('FR-018: the block editor gives the block\'s editor script the renderer\'s answer, before the script', function () {
    $type = WP_Block_Type_Registry::get_instance()->get_registered('corex/newsletter-signup');
    $handle = $type->editor_script_handles[0] ?? '';
    $before = wp_scripts()->get_data($handle, 'before');

    do_action('enqueue_block_editor_assets');

    $available = Boot::app()->container()->make(NewsletterSignupRenderer::class)->available();
    $added = wp_scripts()->get_data($handle, 'before');
    wp_scripts()->add_data($handle, 'before', $before);

    expect($handle)->not->toBe('')
        ->and($added)->toBeArray()
        ->and($added)->toContain('window.corexNewsletterSignup = ' . wp_json_encode(['available' => $available]) . ';');
});
