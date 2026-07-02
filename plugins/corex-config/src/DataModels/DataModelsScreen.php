<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

use Corex\Admin\AdminPage;
use Corex\Config\Data\DataRegistry;
use Corex\Database\Schema\ManagedTables;
use Corex\Database\Schema\Migrator;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Data Models catalog admin screen (spec 063 Phase 3 + spec 065): a truthful schema catalog of the
 * REAL registered CoreX data models (the {@see DataRegistry} sources) — each model's fields and live
 * record count — with a capability + nonce-gated CSV export per model, a link to the Data explorer for
 * record management, a REAL CSV import dry-run (validation preview only — see {@see DataImportValidator})
 * and a truthful migration overview built from the real {@see ManagedTables} + {@see Migrator}. No
 * fabricated models, counts, dry-runs, or migrations: committing an import needs a per-model write
 * adapter the read-only sources do not expose, and that limit is stated honestly, not hidden.
 */
final class DataModelsScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly DataModelsCatalog $catalog,
        private readonly DataRegistry $registry,
        private readonly ManagedTables $tables,
        private readonly Migrator $migrator,
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
            __('CoreX Data Models', 'corex'),
            __('Data Models', 'corex'),
            'manage_options',
            'corex-data-models',
            [$this, 'render'],
            31,
        );
    }

    public function maybeEnqueue(string $hook): void
    {
        if ($hook !== $this->hook || $this->hook === '') {
            return;
        }

        wp_enqueue_style(
            'corex-data-models',
            plugins_url('assets/data-models.css', COREX_CONFIG_FILE),
            ['corex-admin-shell'],
            '1.0.0',
        );
    }

    public function render(): void
    {
        if (! $this->guard->authorized()) {
            echo $this->page->permissionDenied('data-models');

            return;
        }

        $catalog = $this->catalog->catalog($this->models());

        echo $this->page->open(
            'data-models',
            __('CoreX Data Models', 'corex'),
            __('The data models registered on this site, their fields, and their record counts.', 'corex'),
        );

        if ($catalog['isEmpty']) {
            echo $this->page->state(
                'empty',
                __('No data models registered', 'corex'),
                __('CoreX data models registered by add-ons or apps will appear here.', 'corex'),
            );
            echo $this->page->close();

            return;
        }

        echo $this->statusNotice();
        echo $this->summaryBar($catalog);
        echo $this->importPreviewCard();
        echo '<div class="corex-data-models__list">';
        foreach ($catalog['models'] as $model) {
            echo $this->modelCard($model);
        }
        echo '</div>';
        echo $this->migrationOverview();
        echo $this->page->close();
    }

    /** PRG status notice after an import dry-run (read-only query args). */
    private function statusNotice(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only status after a PRG redirect.
        $status = isset($_GET['corex_status']) ? sanitize_key(wp_unslash($_GET['corex_status'])) : '';
        if ($status === '' || ! str_starts_with($status, 'import-')) {
            return '';
        }

        [$tone, $message] = match ($status) {
            'import-preview'  => ['success', __('Dry-run complete. Review the validation preview below — nothing was imported.', 'corex')],
            'import-nofile'   => ['warning', __('Choose a CSV file to validate.', 'corex')],
            'import-empty'    => ['warning', __('That file had no readable header row.', 'corex')],
            'import-badmodel' => ['error', __('Unknown data model.', 'corex')],
            default           => ['', ''],
        };

        return $message === '' ? '' : $this->page->state($tone, __('CSV import (dry-run)', 'corex'), $message);
    }

    /**
     * The most recent CSV import dry-run preview for this user (a short-lived transient), or nothing.
     * A real validation result — accepted/rejected counts + reasons — that wrote no data.
     */
    private function importPreviewCard(): string
    {
        $preview = get_transient(DataModelsImportController::transientKey(get_current_user_id()));
        if (! is_array($preview) || ! isset($preview['totalRows'])) {
            return '';
        }

        $unknown = ! empty($preview['unknown'])
            ? '<p class="corex-data-models__import-warn">' . sprintf(
                /* translators: %s: comma-separated list of unrecognised column names */
                esc_html__('Unrecognised columns (ignored on import): %s', 'corex'),
                esc_html(implode(', ', (array) $preview['unknown'])),
            ) . '</p>'
            : '';

        $rejected = '';
        foreach ((array) ($preview['rejected'] ?? []) as $row) {
            $rejected .= '<li><code>' . esc_html__('line', 'corex') . ' ' . (int) $row['line'] . '</code> — '
                . esc_html((string) $row['reason']) . '</li>';
        }
        $rejectedBlock = $rejected !== ''
            ? '<details class="corex-data-models__import-rejected"><summary>'
                . esc_html__('Rejected rows', 'corex') . '</summary><ul>' . $rejected . '</ul></details>'
            : '';

        return '<section class="corex-surface corex-data-models__import-preview">'
            . '<header class="corex-data-models__card-head"><h2>' . esc_html__('Import dry-run result', 'corex') . '</h2>'
            . '<code class="corex-data-models__key">' . esc_html((string) ($preview['model'] ?? '')) . '</code></header>'
            . '<p>' . sprintf(
                /* translators: 1: accepted row count, 2: total row count */
                esc_html__('%1$d of %2$d rows would import; the rest were rejected.', 'corex'),
                (int) $preview['accepted'],
                (int) $preview['totalRows'],
            ) . '</p>' . $unknown . $rejectedBlock
            . '<p class="corex-data-models__note-text">'
            . esc_html__('This is a validation preview only — no records were written. Committing an import needs a per-model write adapter, which these read-only sources do not yet expose.', 'corex')
            . '</p></section>';
    }

    /**
     * @param array{count:int,totalRecords:int} $catalog
     */
    private function summaryBar(array $catalog): string
    {
        return '<div class="corex-data-models__summary">'
            . $this->stat(__('Models', 'corex'), (string) $catalog['count'])
            . $this->stat(__('Records', 'corex'), (string) $catalog['totalRecords'])
            . '</div>';
    }

    private function stat(string $label, string $value): string
    {
        return '<div class="corex-data-models__summary-card"><p class="corex-data-models__summary-label">'
            . esc_html($label) . '</p><p class="corex-data-models__summary-value">' . esc_html($value)
            . '</p></div>';
    }

    /**
     * @param array{key:string,label:string,columns:list<array{id:string,label:string}>,columnCount:int,total:int} $model
     */
    private function modelCard(array $model): string
    {
        $fields = '';
        foreach ($model['columns'] as $column) {
            $fields .= '<tr><td><code>' . esc_html($column['id']) . '</code></td>'
                . '<td>' . esc_html($column['label']) . '</td></tr>';
        }

        $export = wp_nonce_url(
            admin_url('admin-post.php?action=corex_data_export&source=' . rawurlencode($model['key'])),
            'corex_data_export',
        );
        $explorer = admin_url('admin.php?page=corex-data');

        return sprintf(
            '<section class="corex-surface corex-data-models__card" id="corex-model-%1$s">'
            . '<header class="corex-data-models__card-head"><h2>%2$s</h2>'
            . '<code class="corex-data-models__key">%3$s</code>'
            . '<span class="corex-data-models__count">%4$s</span></header>'
            . '<table class="corex-data-models__fields"><thead><tr><th>%5$s</th><th>%6$s</th></tr></thead>'
            . '<tbody>%7$s</tbody></table>'
            . '<p class="corex-data-models__actions">'
            . '<a class="button" href="%8$s">%9$s</a> '
            . '<a href="%10$s">%11$s</a></p>%12$s</section>',
            esc_attr($model['key']),
            esc_html($model['label']),
            esc_html($model['key']),
            sprintf(
                /* translators: %d: number of records in the model */
                esc_html(_n('%d record', '%d records', $model['total'], 'corex')),
                (int) $model['total'],
            ),
            esc_html__('Field', 'corex'),
            esc_html__('Label', 'corex'),
            $fields,
            esc_url($export),
            esc_html__('Export CSV', 'corex'),
            esc_url($explorer),
            esc_html__('Manage records', 'corex'),
            $this->importForm($model['key']),
        );
    }

    /** The per-model CSV import dry-run form (capability + nonce-gated; validates, never writes). */
    private function importForm(string $modelKey): string
    {
        return '<form class="corex-data-models__import" method="post" enctype="multipart/form-data" action="'
            . esc_url(admin_url('admin-post.php')) . '">'
            . '<input type="hidden" name="action" value="' . esc_attr(DataModelsImportController::ACTION) . '" />'
            . '<input type="hidden" name="corex_model" value="' . esc_attr($modelKey) . '" />'
            . wp_nonce_field(DataModelsImportController::ACTION, DataModelsImportController::NONCE, true, false)
            . '<label class="corex-data-models__import-label">' . esc_html__('Validate a CSV (dry-run)', 'corex')
            . ' <input type="file" name="corex_import" accept=".csv,text/csv" /></label>'
            . '<button type="submit" class="button">' . esc_html__('Run dry-run', 'corex') . '</button></form>';
    }

    /**
     * A truthful migration overview from the real managed-table registry: each CoreX-managed table and
     * whether it exists in the database, or an honest empty state. No fabricated pending migrations.
     */
    private function migrationOverview(): string
    {
        $tables = $this->tables->all();

        if ($tables === []) {
            return '<section class="corex-surface corex-data-models__note">'
                . '<p class="corex-data-models__note-title">' . esc_html__('Migrations', 'corex') . '</p>'
                . '<p class="corex-data-models__note-text">'
                . esc_html__('No CoreX-managed database tables are registered on this site — the data models above are backed by WordPress post types. Managed tables and their schema state appear here when an app registers them.', 'corex')
                . '</p></section>';
        }

        $rows = '';
        foreach ($tables as $table) {
            $name    = $table->name;
            $exists  = $name !== '' && $this->migrator->exists($name);
            $state   = $exists ? __('Installed', 'corex') : __('Pending', 'corex');
            $rows   .= '<li class="corex-data-models__migration is-' . ($exists ? 'ok' : 'pending') . '">'
                . '<code>' . esc_html($name) . '</code><span>' . esc_html($state) . '</span></li>';
        }

        return '<section class="corex-surface corex-data-models__note">'
            . '<p class="corex-data-models__note-title">' . esc_html__('Migrations', 'corex') . '</p>'
            . '<ul class="corex-data-models__migrations">' . $rows . '</ul></section>';
    }

    /**
     * The real registered models from the DataRegistry. Each source that fails to report is skipped
     * rather than faked, so the catalog only ever shows models that truthfully answered.
     *
     * @return list<array{key:string,label:string,columns:list<array{id:string,label:string}>,total:int}>
     */
    private function models(): array
    {
        $models = [];

        foreach ($this->registry->all() as $source) {
            try {
                $models[] = [
                    'key'     => $source->key(),
                    'label'   => $source->label(),
                    'columns' => $source->columns(),
                    'total'   => $source->total(),
                ];
            } catch (\Throwable) {
                continue;
            }
        }

        return $models;
    }
}
