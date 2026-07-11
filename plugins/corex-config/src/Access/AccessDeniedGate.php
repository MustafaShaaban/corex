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
}
