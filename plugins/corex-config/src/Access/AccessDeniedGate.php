<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Access;

defined('ABSPATH') || exit;

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

        wp_die(
            '<h1>' . esc_html__('You don’t have access to this area', 'corex') . '</h1><p>'
            . sprintf(
                /* translators: %s: the required WordPress capability. */
                esc_html__('Your role doesn’t include the %s capability CoreX requires. Ask a site administrator to grant it if you need this screen.', 'corex'),
                '<code>manage_options</code>',
            )
            . '</p><p>' . esc_html__('This attempt was recorded in the CoreX access audit log.', 'corex') . '</p>'
            . '<p><a href="' . esc_url(admin_url()) . '">' . esc_html__('Back to Dashboard', 'corex') . '</a></p>',
            esc_html__('Access denied', 'corex'),
            ['response' => 403],
        );
    }
}
