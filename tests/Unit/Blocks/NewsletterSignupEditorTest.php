<?php

/**
 * The newsletter signup block's editor is told whether the newsletter add-on is on (spec 136,
 * FR-018): with it off the page prints the renderer's note that signup is not available, and the
 * editor, drawing the block in place, must draw that note too. The renderer is the one source of
 * the answer.
 *
 * @package Corex\Tests\Unit\Blocks
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Blocks\NewsletterSignupEditor;
use Corex\Blocks\NewsletterSignupRenderer;
use Corex\Multisite\SingleSiteMultisiteContext;
use Corex\Multisite\WpPluginActivationInspector;
use Corex\Security\NoFormChallenge;

function newsletterEditor(bool $active): NewsletterSignupEditor
{
    Functions\when('get_option')->alias(static fn (string $name, $default = false) => $name === 'active_plugins'
        ? ($active ? ['corex-newsletter/corex-newsletter.php'] : [])
        : $default);

    return new NewsletterSignupEditor(new NewsletterSignupRenderer(
        new WpPluginActivationInspector(new SingleSiteMultisiteContext()),
        new NoFormChallenge(),
    ));
}

it('FR-018: tells the block\'s editor script whether the add-on is on, before the script runs', function (bool $active, string $json) {
    $added = [];
    Functions\when('generate_block_asset_handle')->justReturn('corex-newsletter-signup-editor-script');
    Functions\when('wp_json_encode')->alias(static fn (mixed $value): string => (string) json_encode($value));
    Functions\when('wp_add_inline_script')->alias(static function (string $handle, string $data, string $position) use (&$added): bool {
        $added[] = [$handle, $data, $position];

        return true;
    });

    newsletterEditor($active)->enqueue();

    expect($added)->toBe([[
        'corex-newsletter-signup-editor-script',
        'window.corexNewsletterSignup = ' . $json . ';',
        'before',
    ]]);
})->with([
    'add-on on' => [true, '{"available":true}'],
    'add-on off' => [false, '{"available":false}'],
]);
