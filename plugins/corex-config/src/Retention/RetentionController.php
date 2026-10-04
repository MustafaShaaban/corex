<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Retention;

use Corex\Admin\StandalonePage;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * Handles the retention actions (spec 065): save the window, and prune old submissions. Both are
 * `admin_post` handlers gated by the shared {@see AdminGuard} (capability + nonce). Pruning additionally
 * requires the confirmation box — it never deletes without an explicit preview + confirm — and an
 * action the form offers; anything else is refused before the retention service is reached. PRG redirects
 * back to the Submissions Inbox with a status and, after a prune, the action that ran and how many
 * submissions it handled, which is all the screen has to report the result from.
 */
final class RetentionController
{
    public const SAVE_ACTION  = 'corex_retention_save';
    public const PRUNE_ACTION = 'corex_retention_prune';
    public const NONCE        = 'corex_retention_nonce';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly SubmissionRetention $retention,
        private readonly RetentionSettings $settings,
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

        $action = isset($_POST['corex_retention_action'])
            ? sanitize_key(wp_unslash($_POST['corex_retention_action']))
            : 'trash';
        if (! $this->settings->isAction($action)) {
            $this->redirect('retention-invalid');

            return;
        }

        $includeTest = isset($_POST['corex_include_test']) && $_POST['corex_include_test'] === '1';
        $removed = $this->retention->prune($action, $includeTest);
        $this->redirect('retention-pruned', ['corex_count' => $removed, 'corex_action' => $action]);
    }

    private function assertAllowed(string $action): void
    {
        if (! $this->guard->verifiedPost(self::NONCE, $action)) {
            status_header(403);
            nocache_headers();
            header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
            echo StandalonePage::fromCore()->notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
                __('Access denied', 'corex'),
                __('You are not allowed to change retention, or your link expired.', 'corex'),
                admin_url('admin.php?page=corex-submissions'),
                __('Back to Submissions', 'corex'),
            );
            exit;
        }
    }

    /**
     * @param array<string,int|string> $outcome What the screen needs, besides the status, to report what happened.
     */
    private function redirect(string $status, array $outcome = []): void
    {
        $args = ['page' => 'corex-submissions', 'corex_status' => $status] + $outcome;

        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }
}
