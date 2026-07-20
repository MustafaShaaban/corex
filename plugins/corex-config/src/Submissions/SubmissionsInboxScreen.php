<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

defined('ABSPATH') || exit;

use Corex\Access\CorexAbility;
use Corex\Admin\AdminPage;
use Corex\Config\AdminUi\ScreenAsset;
use Corex\Config\Forms\FlowFilterOptions;
use Corex\Config\Retention\RetentionController;
use Corex\Config\Retention\RetentionSettings;
use Corex\Config\Retention\SubmissionRetention;
use Corex\Security\Admin\AdminGuard;

/**
 * Guarded mount for the functional Inbox client and confirmed retention controls.
 */
final class SubmissionsInboxScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly SubmissionRetention $retention,
        private readonly FlowFilterOptions $flows,
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    public function menu(): void
    {
        $this->hook = (string) add_submenu_page(
            'corex-settings',
            __('CoreX Submissions', 'corex'),
            __('Submissions', 'corex'),
            CorexAbility::MANAGE_SUBMISSIONS,
            'corex-submissions',
            [$this, 'render'],
            26,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        $base = dirname(__DIR__, 2);
        $asset = is_file($base . '/build/admin/index.asset.php')
            ? require $base . '/build/admin/index.asset.php'
            : ['dependencies' => [], 'version' => 'dev'];
        $dependencies = array_map('strval', (array) ($asset['dependencies'] ?? []));
        $dependencies[] = 'corex-runtime';

        wp_enqueue_script(
            'corex-submissions-inbox',
            plugins_url('build/admin/index.js', $base . '/corex-config.php'),
            array_values(array_unique($dependencies)),
            (string) ($asset['version'] ?? 'dev'),
            true,
        );
        wp_enqueue_style(
            'corex-submissions-inbox',
            plugins_url('assets/submissions-admin.css', $base . '/corex-config.php'),
            ['corex-admin-shell'],
            ScreenAsset::version($base . '/assets/submissions-admin.css'),
        );
        wp_localize_script('corex-submissions-inbox', 'corexSubmissions', [
            'restUrl' => esc_url_raw(rest_url('corex/v1/submissions')),
            'nonce' => wp_create_nonce('wp_rest'),
            // Real form names to filter by. The inbox asked for a numeric flow ID, which nobody
            // knows. Empty when the forms add-on is absent — the filter drops, the screen works.
            'flows' => $this->flows->all(),
        ]);
        wp_set_script_translations('corex-submissions-inbox', 'corex', $base . '/languages');
    }

    public function render(): void
    {
        if (! $this->authorized()) {
            echo $this->page->permissionDenied('submissions');

            return;
        }

        echo $this->page->open(
            'submissions',
            __('CoreX Submissions', 'corex'),
            __('Search, assign, reply, export, and retain every accessible real or marked-test flow response.', 'corex'),
        );
        echo '<div id="corex-submissions-app" aria-live="polite"></div>';
        echo $this->retentionNotice() . $this->retentionPanel();
        echo $this->page->close();
    }

    private function authorized(): bool
    {
        return $this->guard->authorized(CorexAbility::MANAGE_SUBMISSIONS)
            || $this->guard->authorized('manage_options');
    }

    private function retentionPanel(): string
    {
        $days = $this->retention->days();
        $preview = $this->retention->preview();
        $status = $preview['enabled']
            ? sprintf(
                /* translators: 1: retention days, 2: matching submissions */
                __('%1$d-day policy · %2$d currently due', 'corex'),
                $days,
                (int) $preview['count'],
            )
            : __('Retention is off; submissions are kept indefinitely.', 'corex');
        $prune = $preview['willPrune'] ? $this->pruneForm() : '';

        return '<section class="corex-inbox-retention corex-surface">'
            . '<header><div><p class="corex-inbox__eyebrow">' . esc_html__('Privacy operations', 'corex') . '</p>'
            . '<h2>' . esc_html__('Submission retention', 'corex') . '</h2></div><span>' . esc_html($status) . '</span></header>'
            . '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(RetentionController::SAVE_ACTION) . '" />'
            . wp_nonce_field(RetentionController::SAVE_ACTION, RetentionController::NONCE, true, false)
            . '<label for="corex-retention-days">' . esc_html__('Keep for days (0 = forever)', 'corex') . '</label>'
            . '<input id="corex-retention-days" type="number" name="corex_retention_days" min="0" max="'
            . esc_attr((string) RetentionSettings::MAX_DAYS) . '" value="' . esc_attr((string) $days) . '" />'
            . '<button type="submit" class="button button-primary">' . esc_html__('Save policy', 'corex') . '</button>'
            . '</form>' . $prune . '</section>';
    }

    private function pruneForm(): string
    {
        return '<form class="corex-inbox-retention__apply" method="post" action="'
            . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(RetentionController::PRUNE_ACTION) . '" />'
            . wp_nonce_field(RetentionController::PRUNE_ACTION, RetentionController::NONCE, true, false)
            . '<label>' . esc_html__('Action', 'corex') . '<select name="corex_retention_action" data-corex-select>'
            . '<option value="archive">' . esc_html__('Archive', 'corex') . '</option>'
            . '<option value="trash">' . esc_html__('Move to trash', 'corex') . '</option>'
            . '<option value="anonymize">' . esc_html__('Anonymize personal data', 'corex') . '</option></select></label>'
            . '<label><input type="checkbox" name="corex_include_test" value="1" /> '
            . esc_html__('Include marked-test submissions', 'corex') . '</label>'
            . '<label><input type="checkbox" name="corex_confirm" value="1" /> '
            . esc_html__('Confirm moving due submissions to the recoverable trash.', 'corex') . '</label>'
            . '<button type="submit" class="button">' . esc_html__('Apply retention', 'corex') . '</button></form>';
    }

    private function retentionNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only PRG result.
        $status = isset($_GET['corex_status']) ? sanitize_key(wp_unslash($_GET['corex_status'])) : '';
        if (! str_starts_with($status, 'retention-')) {
            return '';
        }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only PRG result.
        $count = isset($_GET['corex_count']) ? absint(wp_unslash($_GET['corex_count'])) : 0;
        $message = match ($status) {
            'retention-saved' => __('Retention policy saved.', 'corex'),
            'retention-confirm' => __('Confirm the retention action before applying it.', 'corex'),
            'retention-pruned' => sprintf(
                /* translators: %d: submissions moved to trash */
                _n('%d submission moved to trash.', '%d submissions moved to trash.', $count, 'corex'),
                $count,
            ),
            default => '',
        };

        return $message === '' ? '' : $this->page->state('success', __('Retention', 'corex'), $message);
    }
}
