<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

defined('ABSPATH') || exit;

use Corex\Admin\StandalonePage;

/**
 * The safe public behaviour of Maintenance mode (spec 065): when the operations mode is
 * `maintenance`, anonymous front-end visitors receive an accessible 503 maintenance notice — but any
 * signed-in administrator (or admin/login/cron/AJAX/REST context) passes straight through, so the
 * operator can never lock themselves out. It renames no WordPress core files. Public form
 * submissions, which arrive over REST, are closed by {@see MaintenanceSubmissionGuard} (spec 112).
 */
final class MaintenanceGuard
{
    public function __construct(private readonly MaintenanceDecision $decision)
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
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));

        // A complete, self-contained branded document — the anonymous 503 renders with no
        // enqueued CoreX stylesheet, so StandalonePage inlines the tokens + standalone sheet.
        echo $this->page(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
        exit;
    }

    /**
     * The branded Maintenance-mode page as a full, self-contained HTML document.
     * {@see maybeBlock()} owns the headers and terminates the request.
     */
    public function page(): string
    {
        return StandalonePage::fromCore()->document(
            __('We’ll be back soon', 'corex'),
            $this->bodyHtml(),
            'maintenance',
        );
    }

    /**
     * The Maintenance card body. Pure of side effects and of asset access so the markup is
     * unit-testable; {@see StandalonePage} wraps it in the branded document. `get_bloginfo`
     * is feature-detected so the builder is safe to exercise headlessly.
     */
    public function bodyHtml(): string
    {
        $siteName = function_exists('get_bloginfo') ? (string) get_bloginfo('name') : '';
        $eyebrow  = $siteName !== '' ? $siteName : 'Corex';

        return '<main class="corex-standalone__card" role="main">'
            . '<span class="corex-standalone__mark" aria-hidden="true">' . StandalonePage::brandMark() . '</span>'
            . '<p class="corex-standalone__eyebrow">' . esc_html($eyebrow) . '</p>'
            . '<h1 class="corex-standalone__title">' . esc_html__('We’ll be back soon', 'corex') . '</h1>'
            . '<p class="corex-standalone__text">'
            . esc_html__('The site is undergoing brief maintenance. Please check back shortly.', 'corex')
            . '</p><p><span class="corex-standalone__status">'
            . esc_html__('Scheduled maintenance', 'corex') . '</span></p></main>';
    }

    /**
     * Whether this request should be answered with the maintenance notice: Maintenance closes the
     * site to the visitor ({@see MaintenanceDecision}) and the request is a normal front-end one.
     * Pure of output, so it is testable.
     */
    public function shouldBlock(): bool
    {
        // Never intercept admin, cron, AJAX, or REST contexts. Public submissions over REST are
        // closed by MaintenanceSubmissionGuard instead, which answers them as an API should.
        if (is_admin() || wp_doing_cron() || wp_doing_ajax() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return false;
        }

        return $this->decision->closedToCurrentVisitor();
    }
}
