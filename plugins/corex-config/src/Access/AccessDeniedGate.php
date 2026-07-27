<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;
use Corex\Admin\StandalonePage;

/**
 * The menu-level access-denied gate (spec 067, design: "Corex Access & Abilities" → Access denied).
 * WordPress blocks a user who lacks a page's registered capability BEFORE the page callback runs and
 * fires `admin_page_access_denied` right before its generic wp_die — so without this gate the designed
 * denied state could never actually be reached. For CoreX pages only, this hook publishes the denial to
 * the access audit log (`corex_admin_access_denied`) and replaces the generic message with the designed
 * content at a REAL HTTP 403. It never touches non-CoreX pages.
 */
final class AccessDeniedGate
{
    public function __construct(private readonly AdminPage $page)
    {
    }

    public function register(): void
    {
        add_action('admin_page_access_denied', [$this, 'intercept']);
    }

    public function intercept(): void
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only page identity of a request WordPress is already refusing.
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
        if ($page === '' || ! str_starts_with($page, 'corex-')) {
            return;
        }

        // WordPress fires this hook for two different things: a registered page the viewer may not
        // open, and a page that was never registered at all. Before spec 079 the gate treated both
        // as the first, so `?page=corex-nonexistent` told an administrator — at HTTP 403 — that
        // their role lacked `manage_options`, and offered them a form to request access to a screen
        // that does not exist. Three wrong answers at once; see
        // specs/079-admin-errors-access-request/evidence/before/nonexistent-page.md.
        if (! $this->isDenial($page)) {
            $this->notFound();
        }

        do_action('corex_admin_access_denied', $page);

        status_header(403);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        // The menu-level 403 fires before the admin page loads, so no CoreX admin stylesheet
        // is enqueued. StandalonePage inlines the tokens + standalone sheet so the designed
        // request-access surface is fully styled instead of a bare wp_die notice.
        echo StandalonePage::fromCore()->document( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
            __('Access denied', 'corex'),
            '<main class="corex-standalone__card corex-standalone__card--wide" role="main">'
                . $this->page->deniedSurface($page)
                . '</main>',
            'denied',
        );
        exit;
    }

    /**
     * Whether this slug was refused for want of permission, rather than never existing.
     *
     * Read from the two globals WordPress uses to answer the same question in
     * {@see user_can_access_admin_page()}, in the same order, because they are the only place the
     * two causes are still distinguishable by the time this hook fires.
     *
     * `add_submenu_page()` records a page the viewer may not open in `$_wp_submenu_nopriv` and
     * returns **before** reaching `$_registered_pages` — so registration is not viewer-independent
     * and `$_registered_pages` alone cannot answer this. Checking only that global reports every
     * real CoreX screen as missing to exactly the people this gate exists for, which is how the
     * first version of this method was wrong.
     */
    private function isDenial(string $page): bool
    {
        $parent = get_admin_page_parent();

        if (isset($GLOBALS['_wp_menu_nopriv'][$page])) {
            return true;
        }

        foreach ((array) ($GLOBALS['_wp_submenu_nopriv'] ?? []) as $children) {
            if (is_array($children) && isset($children[$page])) {
                return true;
            }
        }

        return isset($GLOBALS['_registered_pages'][get_plugin_page_hookname($page, $parent)]);
    }

    /**
     * A CoreX address with no CoreX screen behind it.
     *
     * 404, not 403, and no request form: nothing was refused, and there is no ability to ask for.
     */
    private function notFound(): never
    {
        status_header(404);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        echo StandalonePage::fromCore()->notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
            __('That CoreX screen doesn’t exist', 'corex'),
            __('The address you opened doesn’t match any CoreX screen. It may have been renamed, or the add-on that provided it may be inactive.', 'corex'),
            admin_url('admin.php?page=corex-settings'),
            __('Go to CoreX', 'corex'),
        );
        exit;
    }
}
