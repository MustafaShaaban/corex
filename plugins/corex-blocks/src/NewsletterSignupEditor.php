<?php

/**
 * @package Corex\Blocks
 */

declare(strict_types=1);

namespace Corex\Blocks;

defined('ABSPATH') || exit;

/**
 * Tells the newsletter signup block's editor whether signup is available (spec 136, FR-018). The
 * block is drawn in place, without asking the server; with the newsletter add-on off the page
 * prints the renderer's note that signup is not available, so the editor has to know to draw that
 * note too. The answer is the renderer's, written before the block's editor script runs as
 * `window.corexNewsletterSignup = {"available": …}`. BlocksServiceProvider calls `enqueue()` on
 * `enqueue_block_editor_assets`, and makes this only then.
 */
final class NewsletterSignupEditor
{
    private const BLOCK = 'corex/newsletter-signup';

    public function __construct(private readonly NewsletterSignupRenderer $renderer)
    {
    }

    public function enqueue(): void
    {
        wp_add_inline_script(
            generate_block_asset_handle(self::BLOCK, 'editorScript'),
            'window.corexNewsletterSignup = ' . wp_json_encode(['available' => $this->renderer->available()]) . ';',
            'before',
        );
    }
}
