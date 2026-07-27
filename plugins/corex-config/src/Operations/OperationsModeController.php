<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Operations;

use Corex\Admin\StandalonePage;
use Corex\Operations\OperationResult;
use Corex\Security\Admin\AdminGuard;
use DateTimeImmutable;

defined('ABSPATH') || exit;

/**
 * Handles the operations-mode change (spec 065). A single `admin_post` handler gated by the shared
 * {@see AdminGuard} (capability + nonce). Changing to a mode that requires confirmation (production or
 * maintenance) is rejected unless the confirmation box was ticked. On success it persists the mode +
 * audit entry and redirects (POST-redirect-GET) back to Operations & Security with a status. It never
 * fakes a change, never renames WordPress core, and cannot lock the operator out (maintenance always
 * lets signed-in admins through — see {@see MaintenanceGuard}).
 */
final class OperationsModeController
{
    public const ACTION = 'corex_ops_mode';
    public const NONCE  = 'corex_ops_mode_nonce';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly OperationsMode $modes,
        private readonly OperationsModeStore $store,
        private readonly ProductionReadinessSnapshotFactory $readiness,
        private readonly ProductionLaunchService $productionLaunch,
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

        $mode = isset($_POST['corex_mode']) ? sanitize_key(wp_unslash($_POST['corex_mode'])) : '';

        if (! $this->modes->isValid($mode)) {
            $this->redirect('invalid');

            return;
        }

        if ($mode === OperationsMode::PRODUCTION) {
            $this->handleProductionLaunch();

            return;
        }

        $confirmed = isset($_POST['corex_confirm']) && $_POST['corex_confirm'] === '1';
        if ($this->modes->requiresConfirmation($mode) && ! $confirmed) {
            // Propose the mode back, so the confirmation it needs is on screen when the operator
            // arrives — the no-JavaScript second step.
            $this->redirect('confirm', '', $mode);

            return;
        }

        // Asked before applying, because `set()` deliberately makes a no-change indistinguishable
        // from a change in its return value — both answer "what is the mode now". The operator
        // needs the other answer: whether anything happened.
        $wasAlreadyInForce = $this->store->current() === $this->modes->normalize($mode)
            && $this->store->isDeclared();

        $applied = $this->store->set($mode, get_current_user_id());

        // "Saved" over a change that did not happen is a small lie with a real cost: it teaches the
        // operator that the notice means nothing, on the one screen where a notice has to mean
        // something.
        $this->redirect($wasAlreadyInForce ? 'unchanged' : 'saved', $applied);
    }

    private function handleProductionLaunch(): void
    {
        $now      = new DateTimeImmutable('now');
        $actorId  = get_current_user_id();
        $snapshot = $this->readiness->fromCurrentSite($now);
        $preview  = $this->productionLaunch->preview($snapshot, $actorId, $now);
        $phrase   = isset($_POST['corex_confirm_phrase'])
            ? sanitize_text_field(wp_unslash($_POST['corex_confirm_phrase']))
            : '';

        if ($phrase !== ProductionLaunchService::REQUIRED_PHRASE) {
            $this->redirect('production_confirm', '', OperationsMode::PRODUCTION);

            return;
        }

        $result = $this->productionLaunch->apply(new ProductionLaunchRequest(
            snapshot: $snapshot,
            actorId: $actorId,
            now: $now,
            override: new ProductionLaunchOverride($preview->confirmation, $phrase),
        ));

        $this->redirect(
            $result->state === OperationResult::STATE_COMPLETED ? 'saved' : 'blocked',
            OperationsMode::PRODUCTION,
        );
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
