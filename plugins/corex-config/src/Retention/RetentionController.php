<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * Handles the retention actions (spec 065): save the window, and prune old submissions. Both are
 * `admin_post` handlers gated by the shared {@see AdminGuard} (capability + nonce). Pruning additionally
 * requires the confirmation box — it never deletes without an explicit preview + confirm. PRG redirects
 * back to the Submissions Inbox with a status.
 */
final class RetentionController
{
    public const SAVE_ACTION  = 'corex_retention_save';
    public const PRUNE_ACTION = 'corex_retention_prune';
    public const NONCE        = 'corex_retention_nonce';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly SubmissionRetention $retention,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::SAVE_ACTION, [$this, 'save']);
        add_action('admin_post_' . self::PRUNE_ACTION, [$this, 'prune']);
    }

    public function save(): void
    {
        $this->assertAllowed(self::SAVE_ACTION);

        $days = isset($_POST['corex_retention_days']) ? (int) $_POST['corex_retention_days'] : 0;
        $this->retention->setDays($days);

        $this->redirect('retention-saved');
    }

    public function prune(): void
    {
        $this->assertAllowed(self::PRUNE_ACTION);

        $confirmed = isset($_POST['corex_confirm']) && $_POST['corex_confirm'] === '1';
        if (! $confirmed) {
            $this->redirect('retention-confirm');

            return;
        }

        $removed = $this->retention->prune();
        $this->redirect('retention-pruned', $removed);
    }

    private function assertAllowed(string $action): void
    {
        if (! $this->guard->verifiedPost(self::NONCE, $action)) {
            wp_die(
                esc_html__('You are not allowed to change retention, or your link expired.', 'corex'),
                '',
                ['response' => 403],
            );
        }
    }

    private function redirect(string $status, int $count = -1): void
    {
        $args = ['page' => 'corex-submissions', 'corex_status' => $status];
        if ($count >= 0) {
            $args['corex_count'] = $count;
        }

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
