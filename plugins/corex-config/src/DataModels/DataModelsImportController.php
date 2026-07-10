<?php

/**
 * @package Corex\Config
 */

declare(strict_types=1);

namespace Corex\Config\DataModels;

use Corex\Admin\StandalonePage;
use Corex\Config\Data\DataRegistry;
use Corex\Security\Admin\AdminGuard;

defined('ABSPATH') || exit;

/**
 * Legacy admin-post CSV dry-run for a data model (spec 065). The current Spec 068 Data Models workflow uses the
 * REST-backed {@see DataImportService} for immutable dry-run plus checksum-bound commit; this handler remains a
 * compatibility preview path gated by the shared {@see AdminGuard} and intentionally performs no persistence.
 */
final class DataModelsImportController
{
    public const ACTION = 'corex_data_import_dryrun';
    public const NONCE  = 'corex_data_import_nonce';

    private const MAX_ROWS  = 5000;
    private const MAX_BYTES = 2_000_000; // 2 MB
    private const TTL       = 300;

    public function __construct(
        private readonly AdminGuard $guard,
        private readonly DataRegistry $registry,
        private readonly DataImportValidator $validator,
    ) {
    }

    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handle']);
    }

    public static function transientKey(int $userId): string
    {
        return 'corex_import_preview_' . $userId;
    }

    public function handle(): void
    {
        if (! $this->guard->verifiedPost(self::NONCE, self::ACTION)) {
            status_header(403);
            nocache_headers();
            header('Content-Type: text/html; charset=' . get_bloginfo('charset'));
            echo StandalonePage::fromCore()->notice( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- StandalonePage returns a fully-escaped self-contained document.
                __('Access denied', 'corex'),
                __('You are not allowed to import, or your link expired.', 'corex'),
                admin_url('admin.php?page=corex-data-models'),
                __('Back to Data Models', 'corex'),
            );
            exit;
        }

        $modelKey = isset($_POST['corex_model']) ? sanitize_key(wp_unslash($_POST['corex_model'])) : '';
        $source   = $this->registry->find($modelKey);

        if ($source === null) {
            $this->redirect('import-badmodel');

            return;
        }

        $file = $this->uploadedFile();
        if ($file === null) {
            $this->redirect('import-nofile');

            return;
        }

        [$header, $rows] = $this->parseCsv($file);
        if ($header === []) {
            $this->redirect('import-empty');

            return;
        }

        $columnIds = array_map(static fn (array $c): string => (string) $c['id'], $source->columns());
        $preview   = $this->validator->validate($columnIds, $header, $rows);
        $preview['model'] = $modelKey;

        set_transient(self::transientKey(get_current_user_id()), $preview, self::TTL);
        $this->redirect('import-preview');
    }

    /** The safely-validated uploaded CSV temp path, or null. */
    private function uploadedFile(): ?string
    {
        if (! isset($_FILES['corex_import']) || ! is_array($_FILES['corex_import'])) {
            return null;
        }

        $tmp   = isset($_FILES['corex_import']['tmp_name']) ? sanitize_text_field((string) $_FILES['corex_import']['tmp_name']) : '';
        $error = isset($_FILES['corex_import']['error']) ? (int) $_FILES['corex_import']['error'] : UPLOAD_ERR_NO_FILE;
        $size  = isset($_FILES['corex_import']['size']) ? (int) $_FILES['corex_import']['size'] : 0;

        if ($error !== UPLOAD_ERR_OK || $tmp === '' || ! is_uploaded_file($tmp) || $size <= 0 || $size > self::MAX_BYTES) {
            return null;
        }

        return $tmp;
    }

    /**
     * Parse the CSV into [header, rows], bounded. Reading a temp upload with SplFileObject keeps this
     * to the PHP CSV parser — no data is persisted.
     *
     * @return array{0:list<string>,1:list<list<string>>}
     */
    private function parseCsv(string $path): array
    {
        $header = [];
        $rows   = [];

        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [[], []];
        }

        $first = true;
        while (($cells = fgetcsv($handle)) !== false && count($rows) < self::MAX_ROWS) {
            $cells = array_map(static fn ($c): string => (string) $c, (array) $cells);
            if ($first) {
                $header = $cells;
                $first  = false;

                continue;
            }
            $rows[] = $cells;
        }

        fclose($handle);

        return [$header, $rows];
    }

    private function redirect(string $status): void
    {
        wp_safe_redirect(add_query_arg(
            ['page' => 'corex-data-models', 'corex_status' => $status],
            admin_url('admin.php'),
        ));
        exit;
    }
}
