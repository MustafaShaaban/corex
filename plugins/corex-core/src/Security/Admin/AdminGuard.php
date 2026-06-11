<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Security\Admin;

defined('ABSPATH') || exit;

/**
 * The single capability + nonce gate for Corex admin-menu screens.
 *
 * Principle VII's declarative middleware governs Corex *routes* (the REST/AJAX controller
 * lifecycle, which carry a Request/Response through the Pipeline). WordPress admin-menu
 * screens are a different lifecycle — `admin_menu`/`admin_init` page callbacks with no
 * Corex Request — so they are exempt from that pipeline. To keep them from hand-rolling
 * security per screen, every admin screen routes its cap + nonce check through this one
 * helper instead. (DECISIONS #58.)
 */
final class AdminGuard
{
    /**
     * Whether the current user holds the capability (default: site administration).
     */
    public function authorized(string $capability = 'manage_options'): bool
    {
        return current_user_can($capability);
    }

    /**
     * True only when the current user is authorized AND the POST carries a valid nonce
     * for $action in the $field key. The gate an admin screen calls before acting on a
     * submission — so the unslash + sanitize + verify dance lives in exactly one place.
     */
    public function verifiedPost(
        string $field,
        string $action,
        string $capability = 'manage_options'
    ): bool {
        if (! $this->authorized($capability)) {
            return false;
        }

        if (! isset($_POST[$field])) {
            return false;
        }

        $nonce = sanitize_text_field(wp_unslash($_POST[$field]));

        return wp_verify_nonce($nonce, $action) !== false;
    }
}
