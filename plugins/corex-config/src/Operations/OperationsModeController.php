<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

use Corex\Admin\StandalonePage;
use Corex\Security\Admin\AdminGuard;
use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * Handles the operations-mode change (spec 065). A single `admin_post` handler gated by the shared
 * {@see AdminGuard} (capability + nonce). It hands the request to {@see ModeChangeService}, which
 * holds the rules (spec 101), and redirects (POST-redirect-GET) back to Operations & Security with
 * what the service reported. It never fakes a change, never renames WordPress core, and cannot lock
 * the operator out (maintenance always lets signed-in admins through — see {@see MaintenanceGuard}).
 */
final class OperationsModeController
{
    public const ACTION = 'corex_ops_mode';
    public const NONCE  = 'corex_ops_mode_nonce';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly ModeChangeService $changes,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public function handle(): void
    {
        if (! $this->guard->verifiedPost(self::NONCE, self::ACTION)) {
            status_header(403);
            nocache_headers();
            header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
            echo StandalonePage::fromCore()->notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
                __('Access denied', 'corex'),
                __('You are not allowed to change the operations mode, or your link expired.', 'corex'),
                admin_url('admin.php?page=corex-operations-security'),
                __('Back to Operations & Security', 'corex'),
            );
            exit;
        }

        // The controller reads the request and reports the outcome. What the outcome is — which
        // confirmation a mode owes, whether anything changed, whether a launch is blocked — is
        // decided by the service, so the command line is held to exactly the same rules.
        $result = $this->changes->apply(new ModeChangeRequest(
            mode: isset($_POST['corex_mode']) ? sanitize_key(wp_unslash($_POST['corex_mode'])) : '',
            actorId: get_current_user_id(),
            now: new DateTimeImmutable('now'),
            acknowledged: isset($_POST['corex_confirm']) && $_POST['corex_confirm'] === '1',
            phrase: isset($_POST['corex_confirm_phrase'])
                ? sanitize_text_field(wp_unslash($_POST['corex_confirm_phrase']))
                : '',
        ));

        // The result's status is the screen's own vocabulary. "Unchanged" stays distinct from
        // "saved" all the way to the notice: "saved" over a change that did not happen teaches
        // the operator that the notice means nothing, on the one screen where it has to.
        $this->redirect($result->status, $result->applied, $result->proposed);
    }

    /**
     * Back to the screen, saying what happened — and, when a confirmation is still owed, proposing
     * the mode that owes it.
     *
     * `mode` is what makes the form usable without JavaScript. The operator picks Production,
     * submits, and the confirmation they never saw is missing; rather than a dead end, they land
     * back on the form with Production proposed and its confirmation on screen. Two steps, and the
     * second one asks the right question. With JavaScript the swap happened inline and this path is
     * never taken.
     *
     * `corex_mode` (the applied mode, for the notice) and `mode` (the proposed mode, for the form)
     * are separate arguments on purpose: one describes what happened, the other what to offer next,
     * and a redirect can legitimately need to say both.
     */
    private function redirect(string $status, string $mode = '', string $propose = ''): void
    {
        $args = ['page' => 'corex-operations-security', 'corex_status' => $status];
        if ($mode !== '') {
            $args['corex_mode'] = $mode;
        }
        if ($propose !== '') {
            $args['mode'] = $propose;
            // The mode form lives in the Environment section; landing anywhere else would leave
            // the operator to find it again (FR-002).
            $args['tab'] = 'environment';
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
