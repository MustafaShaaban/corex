<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * Keeps the retired Data screen's address working.
 *
 * `page=corex-data` and `page=corex-data-models` were the same screen: both mounted the identical
 * DataExplorer from a structurally identical config against the same REST base, so the only real
 * differences were the menu label and the capability each one checked. Two entries for one screen
 * is a tax on everyone reading the menu, so Data Models absorbed it and its Records tab is now the
 * one place records live.
 *
 * This class no longer renders anything. It exists so bookmarks, documentation, and muscle memory
 * still arrive somewhere correct rather than at "you do not have permission" — deleting an address
 * people already use is not a fix.
 */
final class DataAdminScreen
{
    public const LEGACY_PAGE = 'corex-data';

    public const TARGET_PAGE = 'corex-data-models';

    public function register(): void
    {
        // Deliberately wp_loaded, not admin_init. WordPress builds the admin menu at
        // wp-admin/admin.php line 163, and wp-admin/includes/menu.php refuses an unregistered page
        // right there — firing admin_page_access_denied and wp_die(403) — while admin_init does not
        // run until line 180. An admin_init hook is therefore unreachable for a retired page: it
        // 403s before the redirect can fire. wp_loaded runs inside wp-load.php at line 35, ahead of
        // both. (Measured: with admin_init this returned 403 "Access denied", not a redirect.)
        add_action('wp_loaded', [$this, 'redirectLegacyPage']);
    }

    public function redirectLegacyPage(): void
    {
        if (! is_admin()) {
            return;
        }

        // Nonce-free by design: this reads which page was asked for so it can redirect a GET. It
        // changes nothing, and the destination applies its own capability check.
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';

        if (! $this->redirects($page)) {
            return;
        }

        wp_safe_redirect($this->target(), 301);
        exit;
    }

    /** Kept separate from the handler so the rule is testable without exiting the process. */
    public function redirects(string $page): bool
    {
        return $page === self::LEGACY_PAGE;
    }

    /** The Records tab, since browsing records is what the old screen did. */
    public function target(): string
    {
        return admin_url('admin.php?page=' . self::TARGET_PAGE . '&tab=records');
    }
}
