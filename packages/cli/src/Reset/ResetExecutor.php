<?php

/**
 * @package Corex\Cli
 */

declare(strict_types=1);

namespace Corex\Cli\Reset;

defined('ABSPATH') || exit;

use Corex\Provisioning\PageDisposition;
use Corex\Provisioning\PageRecord;
use WP_CLI;

/**
 * Applies reset actions against WordPress — the side-effecting boundary, kept apart from
 * the pure planner/gate. The soft arms (deactivate add-on, delete option, put back or remove a
 * page a kit touched, put back the reading settings) are ordinary WP calls. The destructive `db-wipe` arm restores the fresh Corex
 * starter and is reachable only via a `db-wipe` action, which the planner emits only for a
 * full reset, which the command runs only when the gate permits (spec 025 FR-005/007/009).
 */
final class ResetExecutor
{
    public function __construct(private readonly string $themeSlug = 'corex')
    {
    }

    public function apply(ResetAction $action, bool $networkWide = false): void
    {
        match ($action->kind) {
            ResetAction::DEACTIVATE_ADDON => $this->deactivate($action->target, $networkWide),
            ResetAction::DELETE_OPTION    => $this->deleteOption($action->target),
            ResetAction::REMOVE_DEMO      => $this->removeDemo((int) $action->target),
            ResetAction::RESTORE_PAGE     => $this->restorePage((int) $action->target),
            ResetAction::EMPTY_PAGE       => $this->emptyPage((int) $action->target),
            ResetAction::KEEP_PAGE        => $this->keepPage((int) $action->target),
            ResetAction::RESTORE_READING  => $this->restoreReading(),
            ResetAction::DB_WIPE          => $this->wipeDatabase(),
        };
    }

    private function deactivate(string $pluginFile, bool $networkWide): void
    {
        if (! function_exists('deactivate_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        deactivate_plugins($pluginFile, false, $networkWide);
    }

    private function deleteOption(string $key): void
    {
        delete_option($key);
    }

    private function removeDemo(int $pageId): void
    {
        $persisted = (string) get_post_meta($pageId, PageDisposition::META, true);

        // A page the owner had is never deleted, whatever the plan said (spec 041 FR-008, spec 115): an
        // adopted page is emptied and untracked, a replaced one is kept and untracked.
        if ($persisted === PageDisposition::PERSISTED_ADOPTED) {
            $this->emptyPage($pageId);

            return;
        }

        if ($persisted === PageDisposition::PERSISTED_REPLACED) {
            $this->keepPage($pageId);

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
     * Put a page the owner had back as the kit recorded it before changing it: same ID, same address.
     * WordPress keeps the kit's version as a revision, as it does for any update.
     */
    private function restorePage(int $pageId): void
    {
        $record = get_post_meta($pageId, PageRecord::META, true);

        if (! PageRecord::isRecord($record)) {
            $this->keepPage($pageId);

            return;
        }

        // Where WordPress filters a post's markup on its way in (for anybody who may not post unfiltered
        // HTML), an owner's embed or form would come back stripped. The record is what the owner's page
        // held, so it goes back unfiltered, and the filters are put back as they were.
        $filtered = has_filter('content_save_pre', 'wp_filter_post_kses') !== false;
        kses_remove_filters();
        try {
            wp_update_post(PageRecord::restoreArgs($pageId, $record));
        } finally {
            if ($filtered) {
                kses_init_filters();
            }
        }

        if ($record['template'] === '') {
            delete_post_meta($pageId, PageRecord::TEMPLATE_META);
        } else {
            update_post_meta($pageId, PageRecord::TEMPLATE_META, $record['template']);
        }

        $this->untrack($pageId);
    }

    /** A page the owner had, adopted while empty, with nothing recorded: emptied and untracked. */
    private function emptyPage(int $pageId): void
    {
        wp_update_post(['ID' => $pageId, 'post_content' => '']);
        delete_post_meta($pageId, PageDisposition::META);
    }

    /** A page the owner had, replaced before records existed: left as it is and untracked. */
    private function keepPage(int $pageId): void
    {
        $this->untrack($pageId);
    }

    private function untrack(int $pageId): void
    {
        delete_post_meta($pageId, PageDisposition::META);
        delete_post_meta($pageId, PageRecord::META);
    }

    /**
     * Put back the front-page and posts-page settings the kit recorded. A recorded page that no longer
     * exists falls back to showing the latest posts, as a reset did before records existed.
     */
    private function restoreReading(): void
    {
        $reading = get_option(PageRecord::READING_OPTION, null);

        if (! PageRecord::isReadingRecord($reading)) {
            return;
        }

        foreach (PageRecord::READING_KEYS as $key) {
            $value = (string) $reading[$key];

            if ($value === '') {
                delete_option($key);
            } else {
                update_option($key, $value);
            }
        }

        $front = (int) $reading['page_on_front'];
        if ((string) $reading['show_on_front'] === 'page' && ($front <= 0 || ! get_post($front) instanceof \WP_Post)) {
            update_option('show_on_front', 'posts');
            delete_option('page_on_front');
        }
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
