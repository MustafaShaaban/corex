<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

use Corex\Access\AbilityNames;
use Corex\Access\CorexAbility;
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

    private const SCREEN = 'corex-operations-security';

    /** The modes that also need "Run dangerous actions". */
    private const DANGEROUS_MODES = [OperationsMode::PRODUCTION, OperationsMode::MAINTENANCE];

    /** The `corex_return` value that sends the answer back to the Setup Wizard. */
    public const RETURN_SETUP = 'setup';

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
        if (! $this->guard->verifiedPost(self::NONCE, self::ACTION, CorexAbility::MANAGE_OPERATIONS, self::SCREEN)) {
            $this->refuse(__('You are not allowed to change the operations mode, or your link expired.', 'corex'));
        }

        $mode = isset($_POST['corex_mode']) ? sanitize_key(wp_unslash($_POST['corex_mode'])) : '';

        // Production opens the site to everyone and Maintenance closes it: both also need "Run
        // dangerous actions" (spec 117, FR-003). The command line has no person and keeps its rules.
        if (in_array($mode, self::DANGEROUS_MODES, true)
            && ! $this->guard->verifiedPost(self::NONCE, self::ACTION, CorexAbility::RUN_DANGEROUS_ACTIONS, self::SCREEN)) {
            $this->refuse(sprintf(
                /* translators: %s: the name of a CoreX ability, "Run dangerous actions". */
                __('Switching to this mode also needs “%s”.', 'corex'),
                AbilityNames::label(CorexAbility::RUN_DANGEROUS_ACTIONS),
            ));
        }

        // The controller reads the request and reports the outcome. What the outcome is — which
        // confirmation a mode owes, whether anything changed, whether a launch is blocked — is
        // decided by the service, so the command line is held to exactly the same rules.
        $result = $this->changes->apply(new ModeChangeRequest(
            mode: $mode,
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
        // The Setup Wizard's launch step posts this same form (spec 118, US-WIZ-06): the same rules
        // and the same history row, and the answer goes back to the wizard.
        $return = isset($_POST['corex_return']) ? sanitize_key(wp_unslash($_POST['corex_return'])) : '';
        if ($return === self::RETURN_SETUP) {
            $this->redirectToSetup($result->status);
        }

        $this->redirect($result->status, $result->applied, $result->proposed);
    }

    /** The standard refusal, at HTTP 403, with a way back to the screen. */
    private function refuse(string $message): never
    {
        status_header(403);
        nocache_headers();
        header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
        echo StandalonePage::fromCore()->notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
            __('Access denied', 'corex'),
            $message,
            admin_url('admin.php?page=corex-operations-security'),
            __('Back to Operations & Security', 'corex'),
        );
        exit;
    }

    /**
     * Back to the wizard: its Done step after a change, its launch step when a confirmation is still
     * owed or the launch was refused, with the outcome to say.
     */
    private function redirectToSetup(string $status): void
    {
        wp_safe_redirect(add_query_arg([
            'page'         => 'corex-setup',
            'corex_step'   => in_array($status, ['saved', 'unchanged'], true) ? 'done' : 'launch',
            'corex_status' => $status,
        ], admin_url('admin.php')));
        exit;
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
