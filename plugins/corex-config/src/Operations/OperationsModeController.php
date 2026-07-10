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
            $this->redirect('confirm');

            return;
        }

        $applied = $this->store->set($mode, get_current_user_id());
        $this->redirect('saved', $applied);
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
            $this->redirect('production_confirm');

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

    private function redirect(string $status, string $mode = ''): void
    {
        $args = ['page' => 'corex-operations-security', 'corex_status' => $status];
        if ($mode !== '') {
            $args['corex_mode'] = $mode;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
