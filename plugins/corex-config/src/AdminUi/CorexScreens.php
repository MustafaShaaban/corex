<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\AdminUi;

defined('ABSPATH') || exit;

/**
 * The one answer to "is this admin screen ours?" (spec 097, FR-004).
 *
 * Extracted from `CorexAdminAssets`, where it had grown a second consumer that wanted nothing else
 * from that class — `NotificationToolbar` injected the whole asset loader and named the property
 * `$screens`, which is the code saying what it actually needed. Spec 097 would have made a third,
 * so the predicate is its own class now and the asset loader keeps a delegate for callers that
 * already had one.
 *
 * Every consumer must resolve to the same answer or the shell fragments: a screen that counts as
 * ours for the stylesheet but not for the help removal renders the CoreX surface with a WordPress
 * Help tab on top of it.
 *
 * **Static, deliberately, and this is the one place in the shell that is.** Principle IV exists so
 * that collaborators can be substituted — this has none: no state, no I/O, no branching on
 * configuration, just a regex over a string. There is nothing a test would want to fake here, and
 * making it injectable would have meant either a fourth constructor parameter threaded through
 * three classes or a `new` inside a constructor, which is the violation Principle IV actually
 * names. `Corex\Guides\Corex\CorexGuides::all()` is the same shape and the same call.
 */
final class CorexScreens
{
    /**
     * Matches every CoreX-owned admin screen by hook/screen-id, regardless of the menu-title
     * prefix WordPress derives (the toplevel `toplevel_page_corex-settings` Overview, and every
     * submenu — `corex_page_*` or `corex-framework_page_*` depending on how WP sanitises the
     * "COREX FRAMEWORK" menu title — plus any declarative `corex-page-*` option page). Every CoreX
     * submenu lives under the `corex-settings` parent, so its hook/screen id ends in
     * `_page_corex-<slug>`; matching that prefix covers ALL current and future CoreX screens
     * (Overview, Add-ons, Data, Data Models, Forms, Submissions, Email Studio, Operations &
     * Security, Access, Insights, Setup, Settings, Guides, option pages) so the full-bleed shell
     * body class is never missing on a real CoreX screen. Keyed on the slug after `_page_`, so the
     * same check works for both the enqueue hook and the `get_current_screen()` id (which disagree
     * for the submenu pages).
     */
    private const SCREEN_PATTERN = '#(?:^toplevel_page_corex-settings$|_page_corex-[a-z0-9-]+)#';

    public static function supports(string $hook): bool
    {
        return preg_match(self::SCREEN_PATTERN, $hook) === 1;
    }
}
