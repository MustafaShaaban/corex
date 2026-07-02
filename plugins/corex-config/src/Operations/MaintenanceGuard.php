<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

/**
 * The safe public behaviour of Maintenance mode (spec 065): when the operations mode is
 * `maintenance`, anonymous front-end visitors receive an accessible 503 maintenance notice — but any
 * signed-in administrator (or admin/login/cron/AJAX/REST context) passes straight through, so the
 * operator can never lock themselves out. It renames no WordPress core files and changes nothing but
 * the anonymous front-end response while the mode is active.
 */
final class MaintenanceGuard
{
    public function __construct(private readonly OperationsModeStore $store)
    {
    }

    public function register(): void
    {
        add_action('template_redirect', [$this, 'maybeBlock'], 0);
    }

    public function maybeBlock(): void
    {
        if (! $this->shouldBlock()) {
            return;
        }

        nocache_headers();
        header('Retry-After: 3600');
        status_header(503);

        $title   = esc_html__('We&#8217;ll be back soon', 'corex');
        $message = esc_html__('The site is undergoing brief maintenance. Please check back shortly.', 'corex');

        wp_die(
            '<h1>' . $title . '</h1><p>' . $message . '</p>',
            $title,
            ['response' => 503],
        );
    }

    /**
     * Whether an anonymous front-end visitor should see the maintenance notice. Blocks only when the
     * mode is maintenance AND the request is a normal front-end request AND the visitor is not a
     * signed-in administrator — the lockout-prevention contract. Pure of output, so it is testable.
     */
    public function shouldBlock(): bool
    {
        if ($this->store->current() !== OperationsMode::MAINTENANCE) {
            return false;
        }

        // Never intercept admin, cron, AJAX, or REST contexts.
        if (is_admin() || wp_doing_cron() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        // Never block a signed-in administrator — the operator cannot lock themselves out.
        if (is_user_logged_in() && current_user_can('manage_options')) {
            return false;
        }

        return true;
    }
}

