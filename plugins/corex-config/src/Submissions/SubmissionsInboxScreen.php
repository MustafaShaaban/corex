<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Submissions;

use Corex\Admin\AdminPage;
use Corex\Config\Data\SubmissionsReader;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Submissions Inbox (spec 063, Phase 2): a business-friendly view of the REAL stored form
 * submissions, distinct from the raw Data explorer. It reads live `corex_submission` records through
 * {@see SubmissionsReader}, shapes them with the pure {@see SubmissionsInbox}, and renders an escaped
 * list plus a server-rendered detail view (`?submission=ID`). Export reuses the existing
 * capability + nonce-gated CSV handler. No fabricated records — an empty store shows an honest empty
 * state. Read-only display; the only mutation path (export) carries `manage_options` + a nonce.
 */
final class SubmissionsInboxScreen
{
    private const PER_PAGE    = 25;
    private const RECENT_DAYS = 7;

    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly SubmissionsInbox $inbox,
        private readonly SubmissionsReader $reader,
        private readonly \Corex\Config\Retention\SubmissionRetention $retention,
        private readonly \Corex\Config\Retention\RetentionController $retentionController,
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
            'manage_options',
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

        wp_enqueue_style(
            'corex-submissions-admin',
            plugins_url('assets/submissions-admin.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('submissions');

            return;
        }

        $detailId = $this->requestedId();
        if ($detailId > 0) {
            $this->renderDetail($detailId);

            return;
        }

        echo $this->page->open(
            'submissions',
            __('CoreX Submissions', 'corex'),
            __('The real form submissions stored on this site. Manage form definitions in Forms & Flows.', 'corex'),
        );

        $total = $this->safeTotal();
        if ($total <= 0) {
            echo $this->page->state(
                'empty',
                __('No submissions yet', 'corex'),
                __('Submissions from your CoreX forms will appear here once visitors start sending them.', 'corex'),
            );
            echo $this->page->close();

            return;
        }

        $summary = $this->inbox->summary([
            'total'      => $total,
            'recent'     => $this->recentCount(),
            'recentDays' => self::RECENT_DAYS,
        ]);

        echo $this->retentionNotice();
        echo $this->summaryBar($summary);
        echo $this->renderList();
        echo $this->retentionPanel();
        echo $this->page->close();
    }

    private function summaryBar(array $summary): string
    {
        $export = wp_nonce_url(
            admin_url('admin-post.php?action=corex_data_export&source=submissions'),
            'corex_data_export',
        );

        return '<div class="corex-submissions__summary">'
            . $this->stat(__('Total', 'corex'), (string) $summary['total'])
            . $this->stat(
                /* translators: %d: number of days in the recent window */
                sprintf(esc_html__('Last %d days', 'corex'), (int) $summary['recentDays']),
                (string) $summary['recent'],
            )
            . $this->stat(__('Export', 'corex'), '<a class="button" href="' . esc_url($export) . '">'
                . esc_html__('Download CSV', 'corex') . '</a>')
            . '</div>';
    }

    private function stat(string $label, string $valueHtml): string
    {
        return '<div class="corex-submissions__summary-card"><p class="corex-submissions__summary-label">'
            . esc_html($label) . '</p><p class="corex-submissions__summary-value">' . wp_kses_post($valueHtml)
            . '</p></div>';
    }

    private function renderList(): string
    {
        $rows = $this->inbox->rows($this->safePage(1, self::PER_PAGE));

        $body = '';
        foreach ($rows as $row) {
            $detailUrl = admin_url('admin.php?page=corex-submissions&submission=' . (int) $row['id']);
            $body     .= '<tr>'
                . '<td>' . esc_html($row['date']) . '</td>'
                . '<td><code>' . esc_html($row['form']) . '</code></td>'
                . '<td>' . esc_html($row['preview']) . '</td>'
                . '<td><a href="' . esc_url($detailUrl) . '">' . esc_html__('View', 'corex') . '</a></td>'
                . '</tr>';
        }

        return '<table class="corex-submissions__table"><thead><tr>'
            . '<th>' . esc_html__('Received', 'corex') . '</th>'
            . '<th>' . esc_html__('Form', 'corex') . '</th>'
            . '<th>' . esc_html__('Preview', 'corex') . '</th>'
            . '<th>' . esc_html__('Detail', 'corex') . '</th>'
            . '</tr></thead><tbody>' . $body . '</tbody></table>'
            . '<p class="corex-submissions__note">'
            . esc_html__('Showing the most recent submissions. Use CoreX Data for full filtering and search.', 'corex')
            . '</p>';
    }

    private function renderDetail(int $id): void
    {
        $submission = $this->safeFind($id);

        echo $this->page->open(
            'submissions',
            __('Submission detail', 'corex'),
            __('A single stored submission. Fields are shown exactly as received.', 'corex'),
        );

        $back = '<p><a href="' . esc_url(admin_url('admin.php?page=corex-submissions')) . '">'
            . esc_html__('← Back to Submissions', 'corex') . '</a></p>';

        if ($submission === null) {
            echo $back . $this->page->state(
                'error',
                __('Submission not found', 'corex'),
                __('This submission no longer exists or was removed.', 'corex'),
            );
            echo $this->page->close();

            return;
        }

        echo $back . '<section class="corex-surface corex-submissions__detail">'
            . '<header class="corex-submissions__detail-head">'
            . '<code class="corex-submissions__form">' . esc_html($submission['form']) . '</code>'
            . '<span class="corex-submissions__date">' . esc_html($submission['date']) . '</span></header>'
            . '<table class="corex-submissions__fields"><tbody>';

        foreach ($submission['fields'] as $key => $value) {
            $display = is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value;
            echo '<tr><th scope="row"><code>' . esc_html((string) $key) . '</code></th>'
                . '<td>' . nl2br(esc_html($display)) . '</td></tr>';
        }

        echo '</tbody></table></section>' . $this->page->close();
    }

    /**
     * The retention panel (spec 065): the real submission retention window + a dry-run preview of how
     * many stored submissions are older than it, a capability + nonce-gated save form, and a
     * confirmed "prune now" action. Nothing is deleted without the preview + the confirmation box.
     */
    private function retentionPanel(): string
    {
        $days    = $this->retention->days();
        $preview = $this->retention->preview();
        $save    = \Corex\Config\Retention\RetentionController::SAVE_ACTION;
        $prune   = \Corex\Config\Retention\RetentionController::PRUNE_ACTION;
        $nonceName = \Corex\Config\Retention\RetentionController::NONCE;

        $status = $preview['enabled']
            ? sprintf(
                /* translators: 1: retention window in days, 2: number of submissions older than it */
                esc_html__('Keeping submissions for %1$d days. %2$d are older than the window.', 'corex'),
                (int) $days,
                (int) $preview['count'],
            )
            : esc_html__('Retention is off — submissions are kept indefinitely.', 'corex');

        $pruneButton = $preview['willPrune']
            ? '<form class="corex-submissions__retain-prune" method="post" action="'
                . esc_url(admin_url('admin-post.php')) . '">'
                . '<input type="hidden" name="action" value="' . esc_attr($prune) . '" />'
                . wp_nonce_field($prune, $nonceName, true, false)
                . '<label><input type="checkbox" name="corex_confirm" value="1" /> '
                . esc_html__('Confirm: move the submissions above to trash.', 'corex') . '</label> '
                . '<button type="submit" class="button">' . esc_html__('Prune now', 'corex') . '</button></form>'
            : '';

        return '<section class="corex-surface corex-submissions__retain">'
            . '<header class="corex-submissions__retain-head"><h2>' . esc_html__('Data retention', 'corex') . '</h2>'
            . '<span class="corex-submissions__retain-status">' . $status . '</span></header>'
            . '<form class="corex-submissions__retain-form" method="post" action="'
            . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr($save) . '" />'
            . wp_nonce_field($save, $nonceName, true, false)
            . '<label for="corex-retention-days">' . esc_html__('Keep submissions for (days, 0 = forever)', 'corex') . '</label>'
            . '<input id="corex-retention-days" type="number" name="corex_retention_days" min="0" max="'
            . esc_attr((string) \Corex\Config\Retention\RetentionSettings::MAX_DAYS) . '" value="' . esc_attr((string) $days) . '" />'
            . '<button type="submit" class="button button-primary">' . esc_html__('Save', 'corex') . '</button>'
            . '</form>'
            . $pruneButton
            . '<p class="corex-submissions__retain-note">'
            . esc_html__('Pruning moves old submissions to trash (recoverable) after you confirm — never deletes without a preview.', 'corex')
            . '</p></section>';
    }

    /** PRG status notice after a retention save/prune (read-only query args). */
    private function retentionNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status after a PRG redirect.
        $status = isset($_GET['corex_status']) ? sanitize_key(wp_unslash($_GET['corex_status'])) : '';
        if (! str_starts_with($status, 'retention-')) {
            return '';
        }

        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only display value.
        $count = isset($_GET['corex_count']) ? absint(wp_unslash($_GET['corex_count'])) : 0;

        [$tone, $message] = match ($status) {
            'retention-saved'   => ['success', __('Retention window saved.', 'corex')],
            'retention-confirm' => ['warning', __('Tick the confirmation box to prune.', 'corex')],
            'retention-pruned'  => ['success', sprintf(
                /* translators: %d: number of submissions moved to trash */
                _n('%d submission moved to trash.', '%d submissions moved to trash.', $count, 'corex'),
                $count,
            )],
            default => ['', ''],
        };

        return $message === '' ? '' : $this->page->state($tone, __('Data retention', 'corex'), $message);
    }

    /** Read-only display id from the query string; a bare read needs no nonce (WP convention). */
    private function requestedId(): int
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only detail view, no state change.
        return isset($_GET['submission']) ? absint(wp_unslash($_GET['submission'])) : 0;
    }

    private function safeTotal(): int
    {
        try {
            return $this->reader->total();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function recentCount(): int
    {
        try {
            return array_sum($this->reader->dailyCounts(self::RECENT_DAYS));
        } catch (\Throwable) {
            return 0;
        }
    }

    /**
     * @return list<array{id:int,date:string,form:string,fields:array<string,mixed>}>
     */
    private function safePage(int $page, int $perPage): array
    {
        try {
            return $this->reader->page($page, $perPage);
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * @return array{id:int,date:string,form:string,fields:array<string,mixed>}|null
     */
    private function safeFind(int $id): ?array
    {
        try {
            return $this->reader->find($id);
        } catch (\Throwable) {
            return null;
        }
    }
}
