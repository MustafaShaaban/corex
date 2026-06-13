<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

use Corex\Provisioning\PageDisposition;
use WP_CLI;

/**
 * Applies reset actions against WordPress — the side-effecting boundary, kept apart from
 * the pure planner/gate. The soft arms (deactivate add-on, delete option, remove seeded
 * demo) are ordinary WP calls. The destructive `db-wipe` arm restores the fresh Corex
 * starter and is reachable only via a `db-wipe` action, which the planner emits only for a
 * full reset, which the command runs only when the gate permits (spec 025 FR-005/007/009).
 */
final class ResetExecutor
{
    public function __construct(private readonly string $themeSlug = 'corex')
    {
    }

    public function apply(ResetAction $action): void
    {
        match ($action->kind) {
            ResetAction::DEACTIVATE_ADDON => $this->deactivate($action->target),
            ResetAction::DELETE_OPTION    => $this->deleteOption($action->target),
            ResetAction::REMOVE_DEMO      => $this->removeDemo((int) $action->target),
            ResetAction::DB_WIPE          => $this->wipeDatabase(),
        };
    }

    private function deactivate(string $pluginFile): void
    {
        if (! function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins($pluginFile);
    }

    private function deleteOption(string $key): void
    {
        delete_option($key);
    }

    private function removeDemo(int $pageId): void
    {
        // A page the kit only *adopted* (populated a pre-existing empty page the user owned) is never deleted —
        // it is emptied and untracked, so a reset can't destroy a page Corex did not create (spec 041 FR-008).
        if ((string) get_post_meta($pageId, '_corex_kit_page', true) === PageDisposition::PERSISTED_ADOPTED) {
            wp_update_post(['ID' => $pageId, 'post_content' => '']);
            delete_post_meta($pageId, '_corex_kit_page');

            return;
        }

        // A page the kit *created* (or a legacy seeded page): revert the front page the wizard set, then delete it.
        if ((int) get_option('page_on_front') === $pageId) {
            update_option('show_on_front', 'posts');
            delete_option('page_on_front');
        }

        wp_delete_post($pageId, true);
    }

    /**
     * Restore a fresh Corex starter: capture the install identity, reset the database,
     * reinstall a clean WordPress with that identity, and activate only the Corex theme.
     * Runs each step in its own WP-CLI subprocess (a DB reset cannot safely run in-process).
     * A new admin password is generated and reported (the old hash cannot be recovered).
     */
    private function wipeDatabase(): void
    {
        $url   = (string) get_option('siteurl');
        $title = (string) get_option('blogname');
        $admin = $this->firstAdmin();
        $pass  = wp_generate_password(20, true, true);

        WP_CLI::runcommand('db reset --yes', ['launch' => true, 'exit_error' => true]);

        WP_CLI::runcommand(sprintf(
            'core install --url=%s --title=%s --admin_user=%s --admin_email=%s --admin_password=%s --skip-email',
            escapeshellarg($url),
            escapeshellarg($title !== '' ? $title : 'Corex'),
            escapeshellarg($admin['login']),
            escapeshellarg($admin['email']),
            escapeshellarg($pass),
        ), ['launch' => true, 'exit_error' => true]);

        WP_CLI::runcommand('theme activate ' . escapeshellarg($this->themeSlug), ['launch' => true, 'exit_error' => true]);

        WP_CLI::log(sprintf('Fresh Corex starter restored. Admin "%s" password: %s', $admin['login'], $pass));
    }

    /**
     * @return array{login:string,email:string}
     */
    private function firstAdmin(): array
    {
        $admins = get_users(['role' => 'administrator', 'number' => 1]);
        $admin  = $admins[0] ?? null;

        return [
            'login' => $admin !== null ? (string) $admin->user_login : 'admin',
            'email' => $admin !== null ? (string) $admin->user_email : (string) get_option('admin_email'),
        ];
    }
}
