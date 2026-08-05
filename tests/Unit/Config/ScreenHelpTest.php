<?php

/**
 * Removing WordPress's contextual help from CoreX admin screens (spec 097).
 *
 * The browser proves the outcome — no link, no panel, no reserved space. These prove the mechanism:
 * that the removal is a real unregistration rather than a stylesheet, that it happens at the one
 * lifecycle point late enough to catch somebody else's tabs, and that it never touches a screen
 * that is not ours.
 *
 * @package Corex\Tests\Unit\Config
 */

declare(strict_types=1);

use Brain\Monkey\Functions;
use Corex\Config\AdminUi\ScreenHelp;

/**
 * A stand-in for `WP_Screen` recording what was called on it.
 *
 * A double rather than the real class because `WP_Screen` cannot be constructed outside WordPress,
 * and recording rather than mocking because the assertion worth making is "both, on this screen and
 * not that one" — which reads better as state than as expectation ordering.
 */
function screenDouble(string $id): object
{
    return new class ($id) {
        public bool $tabsRemoved = false;

        public ?string $sidebar = null;

        public function __construct(public string $id)
        {
        }

        public function remove_help_tabs(): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- mirrors WP_Screen.
        {
            $this->tabsRemoved = true;
        }

        public function set_help_sidebar(string $content): void // phpcs:ignore PSR1.Methods.CamelCapsMethodName -- mirrors WP_Screen.
        {
            $this->sidebar = $content;
        }
    };
}

it('registers on admin_head at the last priority, so it beats the render and follows every registrar', function () {
    // Not `current_screen`, which is where spec 084 added tabs: `admin-header.php` fires
    // `admin_head` and only then renders the screen meta, so this is later than every point at
    // which core or another plugin can add help, and still early enough to matter.
    Functions\expect('add_action')
        ->once()
        ->with('admin_head', \Mockery::type('array'), PHP_INT_MAX);

    (new ScreenHelp())->register();
});

it('removes both the tabs and the sidebar on a CoreX screen', function () {
    $screen = screenDouble('corex_page_corex-guides');
    Functions\when('get_current_screen')->justReturn($screen);

    (new ScreenHelp())->removeOnCorexScreens();

    // Both, because `render_screen_meta()` opens its help branch if *either* is non-empty — a
    // sidebar left behind renders the link and an empty panel, which is the invisible wrapper
    // still holding space that FR-006 exists to forbid.
    expect($screen->tabsRemoved)->toBeTrue()
        ->and($screen->sidebar)->toBe('');
});

it('leaves every non-CoreX screen exactly as WordPress left it', function () {
    // These are the screens spec 097 FR-010 promises are untouched, and the browser suite opens all
    // three to check they still have a working Help tab.
    foreach (['edit', 'options-general', 'plugins', 'dashboard'] as $id) {
        $screen = screenDouble($id);
        Functions\when('get_current_screen')->justReturn($screen);

        (new ScreenHelp())->removeOnCorexScreens();

        expect($screen->tabsRemoved)->toBeFalse($id)
            ->and($screen->sidebar)->toBeNull($id);
    }
});

it('does nothing when there is no screen to act on', function () {
    Functions\when('get_current_screen')->justReturn(null);

    // No exception, no fatal: `admin_head` fires on requests where the screen was never set up,
    // and a help remover that fatals there would take the whole admin down with it.
    (new ScreenHelp())->removeOnCorexScreens();
})->throwsNoExceptions();
