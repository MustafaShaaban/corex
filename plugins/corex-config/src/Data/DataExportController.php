<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\Data;

defined('ABSPATH') || exit;

/**
 * Exports the current (filtered) result of a Corex data source to a CSV download (spec 045,
 * US2). `manage_options` + a nonce gate the `admin_post` handler; the CSV assembly is the
 * pure {@see csvFor} (registry + {@see CsvWriter}) so it is unit-testable, while the
 * boundary (`handle`) sets the download headers and streams it. Bounded to a row cap so a
 * large data set never exhausts memory; only the source's declared columns are written, so
 * no internal/secret field can leak.
 */
final class DataExportController
{
    public const MAX_ROWS  = 5000;
    private const PER_BATCH = 100;

    public function __construct(
        private readonly DataRegistry $registry,
        private readonly CsvWriter $writer,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_corex_data_export', [$this, 'handle']);
    }

    public function handle(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You are not allowed to export this data.', 'corex'), '', ['response' => 403]);
        }

        $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
        if (wp_verify_nonce($nonce, 'corex_data_export') === false) {
            wp_die(esc_html__('Your export link expired — reload the page and try again.', 'corex'), '', ['response' => 403]);
        }

        $sourceKey = isset($_GET['source']) ? sanitize_key(wp_unslash($_GET['source'])) : '';
        $csv       = $this->csvFor($sourceKey, $this->queryFromGet());

        if ($csv === null) {
            wp_die(esc_html__('Unknown data source.', 'corex'), '', ['response' => 404]);
        }

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="corex-' . $sourceKey . '.csv"');

        echo $csv; // The CsvWriter has already RFC-4180-escaped every field.
        exit;
    }

    /**
     * The CSV string for a source's current filtered result, or null if the source is
     * unknown. Bounded to MAX_ROWS.
     */
    public function csvFor(string $sourceKey, DataQuery $query): ?string
    {
        $source = $this->registry->find($sourceKey);

        if ($source === null) {
            return null;
        }

        return $this->writer->write($source->columns(), $this->collect($source, $query));
    }

    /**
     * @return list<array<string,scalar>>
     */
    private function collect(DataSource $source, DataQuery $query): array
    {
        $rows = [];

        for ($page = 1; count($rows) < self::MAX_ROWS; $page++) {
            $batch = $this->batch($source, $query, $page);

            if ($batch === []) {
                break;
            }

            foreach ($batch as $row) {
                $rows[] = $row;
                if (count($rows) >= self::MAX_ROWS) {
                    break;
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string,scalar>>
     */
    private function batch(DataSource $source, DataQuery $query, int $page): array
    {
        $pageQuery = DataQuery::from([
            'search'   => $query->search,
            'form'     => $query->filters['form'] ?? '',
            'sort'     => $query->sortColumn,
            'dir'      => $query->sortDir,
            'page'     => $page,
            'per_page' => self::PER_BATCH,
        ]);

        return $source instanceof QueryableDataSource
            ? $source->query($pageQuery)
            : $source->rows($page, self::PER_BATCH);
    }

    private function queryFromGet(): DataQuery
    {
        return DataQuery::from([
            'search' => isset($_GET['search']) ? sanitize_text_field(wp_unslash($_GET['search'])) : '',
            'form'   => isset($_GET['form']) ? sanitize_key(wp_unslash($_GET['form'])) : '',
            'sort'   => isset($_GET['sort']) ? sanitize_key(wp_unslash($_GET['sort'])) : '',
            'dir'    => isset($_GET['dir']) ? sanitize_key(wp_unslash($_GET['dir'])) : '',
        ]);
    }
}
