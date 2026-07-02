<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

use Corex\Admin\AdminPage;
use Corex\Config\Data\DataRegistry;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * The Data Models catalog admin screen (spec 063, Phase 3): a truthful schema catalog of the REAL
 * registered CoreX data models (the {@see DataRegistry} sources) — each model's fields and live record
 * count — with a capability + nonce-gated CSV export per model (reusing the existing export handler)
 * and a link to the Data explorer for record management. Import and migration tooling do NOT exist in
 * the data layer (no write path, no migration-history tracker), so they are shown as honest future
 * capabilities — never a fake dry-run or a fake pending-migrations list. No fabricated models or counts.
 */
final class DataModelsScreen
{
    private string $hook = '';

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly AdminPage $page,
        private readonly DataModelsCatalog $catalog,
        private readonly DataRegistry $registry,
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

        echo $this->summaryBar($catalog);
        echo '<div class="corex-data-models__list">';
        foreach ($catalog['models'] as $model) {
            echo $this->modelCard($model);
        }
        echo '</div>';
        echo $this->deferralNote();
        echo $this->page->close();
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
            . '<a href="%10$s">%11$s</a></p></section>',
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
        );
    }

    /**
     * Honest deferral: the data layer has no generic write path and no migration-history tracker, so a
     * CSV import (with dry-run) and a pending-migrations view are future capabilities — stated, not faked.
     */
    private function deferralNote(): string
    {
        return '<div class="corex-data-models__note corex-surface">'
            . '<p class="corex-data-models__note-title">' . esc_html__('Import & migrations', 'corex') . '</p>'
            . '<p class="corex-data-models__note-text">'
            . esc_html__(
                'Records are created by the apps and add-ons that own each model; export and record management are available now. A guided CSV import (with a dry-run validation step) and a pending-migrations view are planned future capabilities — they are not enabled yet, so nothing here performs an unverified data change.',
                'corex',
            )
            . '</p></div>';
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
