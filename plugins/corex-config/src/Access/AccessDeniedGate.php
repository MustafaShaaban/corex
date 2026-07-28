<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

use Corex\Admin\AdminPage;
use Corex\Admin\Errors\AdminErrorKind;
use Corex\Admin\Errors\AdminErrorPresenter;
use Corex\Admin\StandalonePage;

/**
 * The menu-level access-denied gate (spec 067, design: "Corex Access & Abilities" → Access denied).
 * WordPress blocks a user who lacks a page's registered capability BEFORE the page callback runs and
 * fires `admin_page_access_denied` right before its generic wp_die — so without this gate the designed
 * denied state could never actually be reached. For CoreX pages, this hook publishes the denial to
 * the access audit log (`corex_admin_access_denied`) and serves the designed request-access surface
 * at a REAL HTTP 403.
 *
 * **This class is no longer the whole of CoreX's error surface.** It used to say "it never touches
 * non-CoreX pages", and that was true in a way nobody had priced: measured on the running install,
 * nine of eleven admin addresses still rendered WordPress's white box, two of them CoreX's own
 * post-type screens (`specs/083-admin-error-surface/evidence/before/refusal-matrix.md`). Spec 083
 * added {@see \Corex\Admin\Errors\AdminDieHandler}, which catches every other human-facing admin
 * refusal. What stays here is the part that address-specific knowledge makes possible and a generic
 * handler cannot do: telling a refused CoreX screen apart from a CoreX address that was never a
 * screen, and offering a request form for an ability that actually exists.
 */
final class AccessDeniedGate
{
    public function __construct(
        private readonly AdminPage $page,
        private readonly AdminErrorPresenter $presenter,
    ) {
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

        // Rendered through the shared presenter, carrying its own body: the standard anatomy has no
        // request-access form in it, and this is the one refusal where a form belongs, because this
        // is the only one that knows which CoreX ability was refused. Everything else about the
        // page — the frame, the status, the styling — comes from the same place as every other
        // refusal, so the two cannot drift apart the way spec 079's six hand-written documents did.
        echo $this->presenter->document( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- the presenter returns a fully-escaped self-contained document.
            $this->presenter
                ->make(AdminErrorKind::Denied, 403)
                ->withBody($this->page->deniedSurface($page)),
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
        if (isset($GLOBALS['_wp_menu_nopriv'][$page])) {
            return true;
        }

        foreach ((array) ($GLOBALS['_wp_submenu_nopriv'] ?? []) as $children) {
            if (is_array($children) && isset($children[$page])) {
                return true;
            }
        }

        // The slug is matched as the segment after `_page_` rather than by rebuilding the hook name
        // with `get_plugin_page_hookname()`. That function derives the prefix from
        // `$admin_page_hooks`, which is only fully populated inside a real admin request — so
        // reconstructing the key would make this answer depend on how the process was started.
        foreach (array_keys((array) ($GLOBALS['_registered_pages'] ?? [])) as $hookname) {
            if (str_ends_with((string) $hookname, '_page_' . $page)) {
                return true;
            }
        }

        return false;
    }

    /**
     * A CoreX address with no CoreX screen behind it.
     *
     * 404, not 403, and no request form: nothing was refused, and there is no ability to ask for.
     *
     * Kept on `StandalonePage::notice()` rather than moved to the shared presenter, which the
     * denial above now uses. The presenter's not-found copy has to speak for any screen on the
     * site; here we know the address began `corex-`, so the wording can say so and the way out can
     * point at CoreX rather than the dashboard. `notice()` is the shared short-notice helper five
     * other controllers already use — using it is not the duplication spec 083 set out to remove.
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
