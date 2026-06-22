<?php

/**
 * @package Corex
 */

declare(strict_types=1);

namespace Corex\Media;

defined('ABSPATH') || exit;

/**
 * `wp corex media reset-webp` (spec 062): safely remove CoreX-generated WebP derivatives. It deletes ONLY
 * files tracked in a `_corex_webp` record (so it never removes originals, manually-uploaded WebP, or any
 * untracked file), clears the record afterwards, supports `--dry-run`, and reports scanned/deleted/skipped/
 * failed counts. The same tracked-only deletion runs on `delete_attachment` via {@see forgetAttachment()}.
 *
 * ## OPTIONS
 *
 * [--dry-run]          Report what would be deleted without removing anything.
 * [--all]              Process all attachments with a tracked derivative.
 * [--attachment=<id>]  Only this attachment.
 * [--limit=<n>]        Process at most N (0 = all). Default: 0.
 */
final class WebpResetCommand
{
    /**
     * @param list<string>         $args
     * @param array<string,string> $assoc
     */
    public function __invoke(array $args, array $assoc = []): void
    {
        $dryRun = isset($assoc['dry-run']);
        $only   = isset($assoc['attachment']) ? (int) $assoc['attachment'] : 0;
        $limit  = isset($assoc['limit']) ? max(0, (int) $assoc['limit']) : 0;

        if ($only === 0 && ! isset($assoc['all'])) {
            $this->warn('Refusing to run without a target: pass --all or --attachment=<id> (with optional --dry-run).');

            return;
        }

        $ids = $only > 0 ? [$only] : $this->trackedAttachmentIds($limit);
        $scanned = 0;
        $deleted = 0;
        $skipped = 0;
        $failed  = 0;

        foreach ($ids as $id) {
            $scanned++;
            $path = self::target($this->record($id));

            if ($path === null) {
                $skipped++;
                continue;
            }
            $this->log(sprintf('%s #%d %s', $dryRun ? 'would delete' : 'deleting', $id, $path));

            if ($dryRun) {
                continue;
            }

            if ($this->deleteFile($path)) {
                delete_post_meta($id, WebpMeta::META_KEY);
                $deleted++;
            } else {
                $failed++;
            }
        }

        $this->success(sprintf(
            '%sscanned: %d · deleted: %d · skipped: %d · failed: %d.',
            $dryRun ? '[dry-run] ' : '',
            $scanned,
            $deleted,
            $skipped,
            $failed,
        ));
    }

    /** Delete the tracked derivative for one attachment (called on delete_attachment). */
    public function forgetAttachment(int $attachmentId): void
    {
        $path = self::target($this->record($attachmentId));

        if ($path !== null) {
            $this->deleteFile($path);
        }

        delete_post_meta($attachmentId, WebpMeta::META_KEY);
    }

    /**
     * The generated-WebP path to delete from a tracked record — or null when the record is missing, has no
     * generated path, or that path is not a `.webp` (so only CoreX-generated WebP is ever targeted). Pure.
     *
     * @param array<string,mixed>|null $record
     */
    public static function target(?array $record): ?string
    {
        if ($record === null) {
            return null;
        }

        $path = trim((string) ($record['generated_path'] ?? ''));

        if ($path === '' || ! preg_match('/\.webp$/i', $path)) {
            return null;
        }

        return $path;
    }

    /** @return array<string,mixed>|null */
    private function record(int $attachmentId): ?array
    {
        $meta = get_post_meta($attachmentId, WebpMeta::META_KEY, true);

        return is_array($meta) ? $meta : null;
    }

    /** @return list<int> */
    private function trackedAttachmentIds(int $limit): array
    {
        return array_map('intval', (array) get_posts([
            'post_type'      => 'attachment',
            'post_status'    => 'inherit',
            'fields'         => 'ids',
            'meta_key'       => WebpMeta::META_KEY,
            'posts_per_page' => $limit > 0 ? $limit : 200,
            'no_found_rows'  => true,
        ]));
    }

    private function deleteFile(string $path): bool
    {
        if (! is_file($path)) {
            return true; // already gone — treat as success so the meta is cleared
        }

        return @unlink($path);
    }

    private function log(string $m): void
    {
        if (class_exists('\WP_CLI')) {
            \WP_CLI::log($m);
        }
    }

    private function warn(string $m): void
    {
        if (class_exists('\WP_CLI')) {
            \WP_CLI::warning($m);
        }
    }

    private function success(string $m): void
    {
        if (class_exists('\WP_CLI')) {
            \WP_CLI::success($m);
        }
    }
}
