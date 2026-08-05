<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\AdminUi;

defined('ABSPATH') || exit;

/**
 * Takes WordPress's contextual Help tab off CoreX admin screens (spec 097).
 *
 * ## Why it goes
 *
 * A CoreX screen is a full-bleed product surface: `body.corex-admin-screen` strips wp-admin's
 * padding, hides its footer and paints the residual canvas, so nothing of the host shows through
 * (spec 067). The Help tab was the one piece of core chrome that survived that, and it does not
 * survive it quietly — the panel opens *above* `#wpbody-content`, pushing the entire product down
 * the page. Spec 084 filled it with guides, which was a reasonable use of an empty surface and is
 * still the wrong surface: the same guides are on the Guides screen, searchable, with their steps,
 * and each one links to the screen it describes.
 *
 * ## Why this is a removal and not a stylesheet
 *
 * Hiding `#contextual-help-link-wrap` with CSS leaves the markup, the toggle, its scripts and its
 * reserved height in the page — the defect made invisible rather than absent. `remove_help_tabs()`
 * and an emptied sidebar mean `WP_Screen::render_screen_meta()` never enters its help branch, so
 * the link, the wrapper and the panel are not emitted at all.
 *
 * ## Why `admin_head` at the last possible priority
 *
 * Deleting spec 084's `ContextualHelp` removes the tabs *CoreX* added. It does nothing about tabs
 * WordPress core or a third-party plugin adds to a CoreX screen, and a screen that is clean until
 * somebody activates an unrelated plugin has been tidied, not fixed.
 *
 * `wp-admin/admin-header.php` fires `admin_head` and only then calls `render_screen_meta()`. So
 * this is the last point that still beats the render, and it is later than every point at which
 * help can be registered — `admin_menu`, `load-{hook}`, `current_screen`, and `admin_head` itself
 * at any ordinary priority. Hooking `current_screen`, where spec 084 added its tabs, would run
 * before half of them.
 *
 * ## Why it lives here and not in the Guides add-on
 *
 * `corex-guides` is optional (Principle IX). A removal that shipped with it would hand every CoreX
 * screen its Help tab back the moment somebody deactivated Guides. `corex-config` is always active.
 *
 * Screen Options is untouched: it is a separate branch of the same method, it belongs to the user
 * rather than to the theme of the page, and nothing about it was reported.
 */
final class ScreenHelp
{
    public function register(): void
    {
        add_action('admin_head', [$this, 'removeOnCorexScreens'], PHP_INT_MAX);
    }

    public function removeOnCorexScreens(): void
    {
        if (! function_exists('get_current_screen')) {
            return;
        }

        $screen = get_current_screen();

        if ($screen === null || ! CorexScreens::supports((string) $screen->id)) {
            return;
        }

        $screen->remove_help_tabs();

        // Both, not just the tabs: `render_screen_meta()` opens the help branch if *either* is
        // non-empty, so a sidebar left behind would render the link and an otherwise empty panel —
        // which is the "invisible wrapper still holding space" outcome this spec exists to avoid.
        $screen->set_help_sidebar('');
    }
}
